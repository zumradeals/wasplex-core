<?php

declare(strict_types=1);

namespace App\Modules\Funds\Http\Controllers\Admin;

use App\Modules\Funds\Application\Services\FundContributionService;
use App\Modules\Funds\Infrastructure\Models\FundAuditEvent;
use App\Modules\Funds\Infrastructure\Models\FundQuoteRequest;
use App\Modules\Funds\Infrastructure\Models\FundWish;
use App\Modules\Funds\Infrastructure\Models\FundWishQuote;
use App\Modules\Identity\Infrastructure\Models\ProfessionalSpace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class AdminFundQuotesController
{
    private const PARTNER_KINDS = [
        ProfessionalSpace::KIND_PARTNER,
        ProfessionalSpace::KIND_MERCHANT,
        ProfessionalSpace::KIND_SERVICE_PROVIDER,
        ProfessionalSpace::KIND_HEALTHCARE_INSTITUTION,
        ProfessionalSpace::KIND_FINANCIAL_OPERATOR,
    ];

    public function __construct(private readonly FundContributionService $contributions) {}

    public function partners(Request $request): JsonResponse
    {
        $country = strtoupper((string) $request->query('country', 'CI'));

        $partners = ProfessionalSpace::query()
            ->with(['organization:id,name,country_code,status', 'servicePoints:id,professional_space_id,name,city,area_label,status'])
            ->where('verification_status', ProfessionalSpace::VERIFICATION_VERIFIED)
            ->whereIn('space_kind', self::PARTNER_KINDS)
            ->where('country_code', $country)
            ->whereHas('organization', fn ($query) => $query->where('status', 'active'))
            ->orderBy('trade_name')
            ->orderBy('legal_name')
            ->get()
            ->map(fn (ProfessionalSpace $space): array => [
                'professional_space_id' => $space->id,
                'organization_id' => $space->organization_id,
                'name' => $space->trade_name ?: $space->legal_name ?: $space->organization?->name,
                'space_kind' => $space->space_kind,
                'country_code' => $space->country_code,
                'territory' => $space->territory,
                'service_points' => $space->servicePoints
                    ->where('status', 'active')
                    ->map(fn ($point): array => [
                        'id' => $point->id,
                        'name' => $point->name,
                        'city' => $point->city,
                        'area_label' => $point->area_label,
                    ])->values(),
            ])->values();

        return response()->json(['partners' => $partners]);
    }

    public function requestQuotes(Request $request, FundWish $wish): JsonResponse
    {
        abort_if($wish->status !== FundWish::STATUS_APPROVED, 422, 'Le vœu doit être validé avant toute demande de devis.');
        abort_if($wish->selected_quote_id !== null, 422, 'Un devis a déjà été sélectionné pour ce vœu.');

        $data = $request->validate([
            'professional_space_ids' => ['required', 'array', 'min:1', 'max:10'],
            'professional_space_ids.*' => ['required', 'string', 'distinct', Rule::exists('professional_spaces', 'id')],
            'request_summary' => ['nullable', 'string', 'max:500'],
            'requirements' => ['nullable', 'array'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $spaces = ProfessionalSpace::query()
            ->with('organization')
            ->whereIn('id', $data['professional_space_ids'])
            ->where('verification_status', ProfessionalSpace::VERIFICATION_VERIFIED)
            ->whereIn('space_kind', self::PARTNER_KINDS)
            ->get();

        abort_if($spaces->count() !== count($data['professional_space_ids']), 422, 'Tous les prestataires doivent être des espaces professionnels vérifiés et éligibles.');

        foreach ($spaces as $space) {
            abort_if($space->organization === null || ! $space->organization->isActive(), 422, 'Une organisation partenaire n’est plus active.');
            abort_if(strtoupper((string) $space->country_code) !== strtoupper((string) $wish->country_code), 422, 'Le prestataire doit être dans le même pays que le vœu pour P014-C.');
        }

        $created = DB::transaction(function () use ($request, $wish, $spaces, $data): array {
            $rows = [];
            foreach ($spaces as $space) {
                $quoteRequest = FundQuoteRequest::query()->firstOrCreate([
                    'fund_wish_id' => $wish->id,
                    'organization_id' => $space->organization_id,
                ], [
                    'professional_space_id' => $space->id,
                    'requested_by_account_id' => $request->user()->id,
                    'status' => FundQuoteRequest::STATUS_REQUESTED,
                    'request_summary' => $data['request_summary'] ?? null,
                    'requirements' => $data['requirements'] ?? null,
                    'requested_at' => now(),
                    'expires_at' => $data['expires_at'] ?? null,
                ]);
                $rows[] = $quoteRequest;
            }

            FundAuditEvent::record((string) $request->user()->id, 'FundQuoteRequestsCreated', 'fund_wish', $wish->id, [
                'organizations' => $spaces->pluck('organization_id')->values()->all(),
            ]);

            return $rows;
        });

        return response()->json([
            'created' => collect($created)->map(fn (FundQuoteRequest $item): array => [
                'id' => $item->id,
                'organization_id' => $item->organization_id,
                'status' => $item->status,
            ])->values(),
        ], 201);
    }

    public function selectQuote(Request $request, FundWish $wish, FundWishQuote $quote): JsonResponse
    {
        abort_if($wish->status !== FundWish::STATUS_APPROVED, 422, 'Le vœu doit être validé.');
        abort_if($quote->fund_wish_id !== $wish->id, 404);
        abort_if($quote->status !== FundWishQuote::STATUS_SUBMITTED, 422, 'Ce devis ne peut pas être sélectionné.');
        abort_if($quote->valid_until !== null && $quote->valid_until->isPast(), 422, 'Ce devis a expiré.');

        $wish = DB::transaction(function () use ($request, $wish, $quote): FundWish {
            $wish = FundWish::query()->whereKey($wish->id)->lockForUpdate()->firstOrFail();
            abort_if($wish->selected_quote_id !== null, 422, 'Un devis a déjà été sélectionné.');

            $currentContribution = $this->contributions->wishContributionMinor($wish);
            $percent = (int) $wish->membership()->with('version')->firstOrFail()->version->personal_contribution_percent;
            $calculatedRequired = (int) ceil(((int) $quote->amount_minor) * ($percent / 100));
            $required = max($currentContribution, $calculatedRequired);

            $quote->update([
                'status' => FundWishQuote::STATUS_SELECTED,
                'selected_by_account_id' => $request->user()->id,
                'selected_at' => now(),
            ]);
            FundWishQuote::query()
                ->where('fund_wish_id', $wish->id)
                ->where('id', '!=', $quote->id)
                ->where('status', FundWishQuote::STATUS_SUBMITTED)
                ->update(['status' => FundWishQuote::STATUS_NOT_SELECTED]);

            FundQuoteRequest::query()
                ->where('fund_wish_id', $wish->id)
                ->where('organization_id', $quote->organization_id)
                ->update(['status' => FundQuoteRequest::STATUS_SELECTED]);
            FundQuoteRequest::query()
                ->where('fund_wish_id', $wish->id)
                ->where('organization_id', '!=', $quote->organization_id)
                ->whereIn('status', [FundQuoteRequest::STATUS_REQUESTED, FundQuoteRequest::STATUS_RESPONDED])
                ->update(['status' => FundQuoteRequest::STATUS_NOT_SELECTED]);

            $wish->update([
                'selected_quote_id' => $quote->id,
                'provider_organization_id' => $quote->organization_id,
                'validated_amount_minor' => $quote->amount_minor,
                'required_personal_contribution_minor' => $required,
                'cost_validated_at' => now(),
                'personal_contribution_completed_at' => $currentContribution >= $required ? now() : null,
            ]);

            FundAuditEvent::record((string) $request->user()->id, 'FundQuoteSelected', 'fund_wish', $wish->id, [
                'quote_id' => $quote->id,
                'organization_id' => $quote->organization_id,
                'validated_amount_minor' => $quote->amount_minor,
                'required_personal_contribution_minor' => $required,
            ]);

            return $wish->refresh();
        });

        return response()->json([
            'wish_id' => $wish->id,
            'selected_quote_id' => $wish->selected_quote_id,
            'provider_organization_id' => $wish->provider_organization_id,
            'validated_amount_minor' => $wish->validated_amount_minor,
            'required_personal_contribution_minor' => $wish->required_personal_contribution_minor,
            'cost_validated_at' => $wish->cost_validated_at,
        ]);
    }
}
