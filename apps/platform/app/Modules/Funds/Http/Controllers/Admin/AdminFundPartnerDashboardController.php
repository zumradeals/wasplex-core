<?php

declare(strict_types=1);

namespace App\Modules\Funds\Http\Controllers\Admin;

use App\Modules\Funds\Infrastructure\Models\FundWish;
use App\Modules\Identity\Infrastructure\Models\ProfessionalSpace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminFundPartnerDashboardController
{
    private const PARTNER_KINDS = [
        ProfessionalSpace::KIND_PARTNER,
        ProfessionalSpace::KIND_MERCHANT,
        ProfessionalSpace::KIND_SERVICE_PROVIDER,
        ProfessionalSpace::KIND_HEALTHCARE_INSTITUTION,
        ProfessionalSpace::KIND_FINANCIAL_OPERATOR,
    ];

    public function index(Request $request): JsonResponse
    {
        $country = strtoupper((string) $request->query('country', 'CI'));

        $partners = ProfessionalSpace::query()
            ->with('organization:id,name,country_code,status')
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
            ])->values();

        $wishes = FundWish::query()
            ->with([
                'category:id,name,icon',
                'quoteRequests.organization:id,name',
                'quoteRequests.quote',
                'selectedQuote.organization:id,name',
                'providerOrganization:id,name',
            ])
            ->where('status', FundWish::STATUS_APPROVED)
            ->where('country_code', $country)
            ->latest('reviewed_at')
            ->limit(100)
            ->get()
            ->map(fn (FundWish $wish): array => [
                'id' => $wish->id,
                'reference' => 'FONDS-'.strtoupper(substr((string) $wish->id, -6)),
                'title' => $wish->title,
                'category' => $wish->category === null ? null : [
                    'name' => $wish->category->name,
                    'icon' => $wish->category->icon,
                ],
                'city' => $wish->city,
                'estimated_amount_minor' => $wish->estimated_amount_minor,
                'validated_amount_minor' => $wish->validated_amount_minor,
                'currency' => $wish->currency,
                'selected_quote_id' => $wish->selected_quote_id,
                'provider' => $wish->providerOrganization === null ? null : [
                    'organization_id' => $wish->providerOrganization->id,
                    'name' => $wish->providerOrganization->name,
                ],
                'quote_requests' => $wish->quoteRequests->map(fn ($item): array => [
                    'id' => $item->id,
                    'organization_id' => $item->organization_id,
                    'organization_name' => $item->organization?->name,
                    'status' => $item->status,
                    'requested_at' => $item->requested_at,
                    'expires_at' => $item->expires_at,
                    'quote' => $item->quote === null ? null : [
                        'id' => $item->quote->id,
                        'status' => $item->quote->status,
                        'amount_minor' => $item->quote->amount_minor,
                        'currency' => $item->quote->currency,
                        'lead_time_days' => $item->quote->lead_time_days,
                        'valid_until' => $item->quote->valid_until,
                        'terms' => $item->quote->terms,
                        'notes' => $item->quote->notes,
                        'submitted_at' => $item->quote->submitted_at,
                    ],
                ])->values(),
            ])->values();

        return response()->json([
            'partners' => $partners,
            'wishes' => $wishes,
            'metrics' => [
                'verified_partners' => $partners->count(),
                'open_wishes' => $wishes->whereNull('selected_quote_id')->count(),
                'quotes_received' => $wishes->sum(fn (array $wish): int => collect($wish['quote_requests'])->whereNotNull('quote')->count()),
                'providers_selected' => $wishes->whereNotNull('selected_quote_id')->count(),
            ],
        ]);
    }
}
