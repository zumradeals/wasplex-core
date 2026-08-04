<?php

namespace App\Modules\Campaign\Application\Services;

use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAsset;
use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAssetVersion;
use App\Modules\Campaign\Infrastructure\Models\Campaign;
use App\Modules\Campaign\Infrastructure\Models\CampaignAudience;
use App\Modules\Campaign\Infrastructure\Models\CampaignFunding;
use App\Modules\Campaign\Infrastructure\Models\CampaignQuote;
use App\Modules\Campaign\Infrastructure\Models\CampaignReviewCase;
use App\Modules\Campaign\Infrastructure\Models\CampaignVersion;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class CampaignPresenter
{
    /** @return array<string, mixed> */
    public function campaign(Campaign $campaign): array
    {
        $version = $campaign->activeVersion;
        $quote = $campaign->quotes->first();
        $audience = $campaign->audiences->first();
        $funding = $campaign->funding;
        $review = $campaign->reviewCases->first();

        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'status' => $campaign->status,
            'currentStep' => $campaign->current_step,
            'brand' => [
                'id' => $campaign->brand->id,
                'name' => $campaign->brand->name,
                'primaryColor' => $campaign->brand->activeVersion?->primary_color,
                'secondaryColor' => $campaign->brand->activeVersion?->secondary_color,
            ],
            'version' => $version instanceof CampaignVersion ? $this->version($version) : null,
            'audience' => $audience instanceof CampaignAudience ? $this->audience($audience) : null,
            'quote' => $quote instanceof CampaignQuote ? $this->quote($quote) : null,
            'funding' => $funding instanceof CampaignFunding ? $this->funding($funding) : null,
            'review' => $review instanceof CampaignReviewCase ? [
                'id' => $review->id,
                'submissionNumber' => $review->submission_number,
                'status' => $review->status,
                'reason' => $review->decision_reason,
                'openedAt' => $review->opened_at->toIso8601String(),
                'decidedAt' => $review->decided_at?->toIso8601String(),
            ] : null,
            'submittedAt' => $campaign->submitted_at?->toIso8601String(),
            'createdAt' => $campaign->created_at?->toIso8601String(),
            'updatedAt' => $campaign->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function version(CampaignVersion $version): array
    {
        return [
            'id' => $version->id,
            'version' => $version->version,
            'objective' => $version->objective,
            'headline' => $version->headline,
            'body' => $version->body,
            'callToAction' => $version->call_to_action,
            'destinationUrl' => $version->destination_url,
            'startsOn' => $version->starts_on?->toDateString(),
            'endsOn' => $version->ends_on?->toDateString(),
            'territoryName' => $version->territory_name,
            'radiusKm' => $version->radius_km,
            'selectedClasses' => $version->selected_classes,
            'budgetMinor' => $version->requested_budget_minor,
            'creatives' => $version->creatives->map(fn (CreativeAsset $asset): array => $this->asset($asset))->values(),
        ];
    }

    /** @return array<string, mixed> */
    public function audience(CampaignAudience $audience): array
    {
        return [
            'id' => $audience->id,
            'kind' => $audience->estimate_kind,
            'territoryName' => $audience->territory_name,
            'radiusKm' => $audience->radius_km,
            'selectedClasses' => $audience->selected_classes,
            'estimatedMin' => $audience->estimated_min,
            'estimatedMax' => $audience->estimated_max,
            'notice' => is_array($audience->assumptions) ? ($audience->assumptions['notice'] ?? null) : null,
            'estimatedAt' => $audience->estimated_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function quote(CampaignQuote $quote): array
    {
        return [
            'id' => $quote->id,
            'version' => $quote->version,
            'status' => $quote->status,
            'unit' => $quote->unit,
            'currency' => $quote->currency,
            'grossAmountMinor' => $quote->gross_amount_minor,
            'platformShareMinor' => $quote->platform_share_minor,
            'userShareMinor' => $quote->user_share_minor,
            'cpmMinor' => $quote->cpm_minor,
            'estimatedImpressions' => $quote->estimated_impressions,
            'catalogVersion' => $quote->priceCatalog->version,
            'issuedAt' => $quote->issued_at->toIso8601String(),
            'expiresAt' => $quote->expires_at->toIso8601String(),
            'fundedAt' => $quote->funded_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function funding(CampaignFunding $funding): array
    {
        return [
            'id' => $funding->id,
            'status' => $funding->status,
            'amountMinor' => $funding->amount_minor,
            'unit' => $funding->unit,
            'currency' => $funding->currency,
            'reservationId' => $funding->budgetReservation?->wallet_reservation_id,
            'reservationStatus' => $funding->budgetReservation?->status,
            'reservedAt' => $funding->reserved_at->toIso8601String(),
            'submittedAt' => $funding->submitted_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function asset(CreativeAsset $asset): array
    {
        $metadata = $asset->versions->first();

        return [
            'id' => $asset->id,
            'kind' => $asset->kind->value,
            'name' => $metadata instanceof CreativeAssetVersion && $metadata->label !== null
                ? $metadata->label
                : $asset->original_name,
            'mimeType' => $asset->mime_type,
            'url' => $this->assetUrl($asset),
        ];
    }

    private function assetUrl(CreativeAsset $asset): ?string
    {
        try {
            return Storage::disk($asset->disk)->url($asset->path);
        } catch (Throwable) {
            return null;
        }
    }
}
