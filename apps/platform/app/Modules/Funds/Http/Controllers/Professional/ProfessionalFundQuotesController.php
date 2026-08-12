<?php

declare(strict_types=1);

namespace App\Modules\Funds\Http\Controllers\Professional;

use App\Modules\Funds\Infrastructure\Models\FundAuditEvent;
use App\Modules\Funds\Infrastructure\Models\FundQuoteRequest;
use App\Modules\Funds\Infrastructure\Models\FundWishQuote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ProfessionalFundQuotesController
{
    public function index(Request $request): JsonResponse
    {
        $organizationId = (string) $request->attributes->get('professional_organization_id');

        $requests = FundQuoteRequest::query()
            ->with(['wish.category:id,name,icon', 'quote'])
            ->where('organization_id', $organizationId)
            ->latest('requested_at')
            ->get()
            ->map(function (FundQuoteRequest $item): array {
                if ($item->status === FundQuoteRequest::STATUS_REQUESTED && $item->expires_at !== null && $item->expires_at->isPast()) {
                    $item->update(['status' => FundQuoteRequest::STATUS_EXPIRED]);
                }

                $wish = $item->wish;

                return [
                    'id' => $item->id,
                    'status' => $item->status,
                    'request_summary' => $item->request_summary,
                    'requirements' => $item->requirements,
                    'requested_at' => $item->requested_at,
                    'expires_at' => $item->expires_at,
                    'wish' => [
                        'reference' => 'FONDS-'.strtoupper(substr((string) $wish->id, -6)),
                        'category' => $wish->category === null ? null : [
                            'name' => $wish->category->name,
                            'icon' => $wish->category->icon,
                        ],
                        'title' => $wish->title,
                        'description' => $wish->description,
                        'country_code' => $wish->country_code,
                        'city' => $wish->city,
                        'estimated_amount_minor' => $wish->estimated_amount_minor,
                        'currency' => $wish->currency,
                    ],
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
                ];
            })->values();

        return response()->json(['quote_requests' => $requests]);
    }

    public function submit(Request $request, FundQuoteRequest $quoteRequest): JsonResponse
    {
        $organizationId = (string) $request->attributes->get('professional_organization_id');
        abort_if($quoteRequest->organization_id !== $organizationId, 404);
        abort_if(! in_array($quoteRequest->status, [FundQuoteRequest::STATUS_REQUESTED, FundQuoteRequest::STATUS_RESPONDED], true), 422, 'Cette demande de devis n’est plus ouverte.');
        abort_if($quoteRequest->expires_at !== null && $quoteRequest->expires_at->isPast(), 422, 'Cette demande de devis a expiré.');

        $data = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'valid_until' => ['nullable', 'date', 'after:now'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        abort_if(strtoupper($data['currency']) !== strtoupper((string) $quoteRequest->wish()->value('currency')), 422, 'La devise du devis doit correspondre à celle du vœu.');

        $quote = DB::transaction(function () use ($request, $quoteRequest, $organizationId, $data): FundWishQuote {
            $quote = FundWishQuote::query()->updateOrCreate([
                'quote_request_id' => $quoteRequest->id,
            ], [
                'fund_wish_id' => $quoteRequest->fund_wish_id,
                'organization_id' => $organizationId,
                'submitted_by_account_id' => $request->user()->id,
                'amount_minor' => $data['amount_minor'],
                'currency' => strtoupper($data['currency']),
                'lead_time_days' => $data['lead_time_days'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
                'terms' => $data['terms'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => FundWishQuote::STATUS_SUBMITTED,
                'submitted_at' => now(),
                'selected_by_account_id' => null,
                'selected_at' => null,
            ]);

            $quoteRequest->update([
                'status' => FundQuoteRequest::STATUS_RESPONDED,
                'responded_at' => now(),
            ]);

            FundAuditEvent::record((string) $request->user()->id, 'FundPartnerQuoteSubmitted', 'fund_quote_request', $quoteRequest->id, [
                'organization_id' => $organizationId,
                'quote_id' => $quote->id,
                'amount_minor' => $quote->amount_minor,
            ]);

            return $quote;
        });

        return response()->json([
            'id' => $quote->id,
            'status' => $quote->status,
            'amount_minor' => $quote->amount_minor,
            'currency' => $quote->currency,
            'submitted_at' => $quote->submitted_at,
        ], 201);
    }

    public function decline(Request $request, FundQuoteRequest $quoteRequest): JsonResponse
    {
        $organizationId = (string) $request->attributes->get('professional_organization_id');
        abort_if($quoteRequest->organization_id !== $organizationId, 404);
        abort_if($quoteRequest->status !== FundQuoteRequest::STATUS_REQUESTED, 422, 'Cette demande ne peut plus être refusée.');

        $quoteRequest->update([
            'status' => FundQuoteRequest::STATUS_DECLINED,
            'responded_at' => now(),
        ]);

        FundAuditEvent::record((string) $request->user()->id, 'FundPartnerQuoteDeclined', 'fund_quote_request', $quoteRequest->id, [
            'organization_id' => $organizationId,
        ]);

        return response()->json(['status' => $quoteRequest->status]);
    }
}
