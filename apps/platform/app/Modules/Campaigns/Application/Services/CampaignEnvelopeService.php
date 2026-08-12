<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use App\Modules\Campaigns\Application\Contracts\CampaignEnvelopeContract;
use App\Modules\Campaigns\Application\ValueObjects\CampaignEnvelopeSlot;
use App\Modules\Campaigns\Infrastructure\Models\Campaign;
use App\Modules\Campaigns\Infrastructure\Models\CampaignEnvelopeConsumption;
use App\Modules\Campaigns\Infrastructure\Models\CampaignQuote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class CampaignEnvelopeService implements CampaignEnvelopeContract
{
    public function reserveSlot(string $campaignId, string $economicClass, string $idempotencyKey): CampaignEnvelopeSlot
    {
        $existing = CampaignEnvelopeConsumption::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $this->toSlot($existing);
        }

        $campaign = Campaign::query()->where('status', Campaign::STATUS_APPROVED)->find($campaignId);
        if ($campaign === null) {
            throw new CampaignNotAvailableForDeliveryException;
        }

        $quote = $campaign->currentVersion()?->latestQuote();
        if ($quote === null || $quote->status !== CampaignQuote::STATUS_CONSUMED) {
            throw new CampaignNotAvailableForDeliveryException;
        }

        $classBreakdown = $quote->class_breakdown[$economicClass] ?? null;
        if ($classBreakdown === null) {
            throw new CampaignNotAvailableForDeliveryException;
        }

        $creative = $campaign->currentVersion()?->creative_configuration ?? [];
        $creativeUnits = max(1, (int) ($creative['attention_units'] ?? 1));
        $quotedUnits = max(1, (int) ($classBreakdown['attention_units'] ?? 1));
        if ($creativeUnits !== $quotedUnits) {
            throw new CampaignNotAvailableForDeliveryException;
        }

        return DB::transaction(function () use ($campaign, $quote, $economicClass, $classBreakdown, $idempotencyKey): CampaignEnvelopeSlot {
            CampaignQuote::query()->whereKey($quote->id)->lockForUpdate()->first();

            $committedReward = (int) CampaignEnvelopeConsumption::query()
                ->where('campaign_quote_id', $quote->id)
                ->whereIn('status', [CampaignEnvelopeConsumption::STATUS_RESERVED, CampaignEnvelopeConsumption::STATUS_CAPTURED])
                ->sum('gain_minor');
            $gainMinor = (int) $classBreakdown['gain_unitaire_minor'];

            if ($committedReward + $gainMinor > intdiv($quote->gross_amount_minor, 2)) {
                throw new CampaignEnvelopeExhaustedException;
            }

            $now = Carbon::now('UTC');
            $consumption = CampaignEnvelopeConsumption::query()->create([
                'campaign_quote_id' => $quote->id,
                'economic_class' => $economicClass,
                'status' => CampaignEnvelopeConsumption::STATUS_RESERVED,
                'gain_minor' => $gainMinor,
                'reserved_at' => $now,
                'expires_at' => $now->clone()->addSeconds((int) config('campaigns.envelope_reservation_ttl_seconds')),
                'idempotency_key' => $idempotencyKey,
            ]);

            return $this->toSlot($consumption, $campaign, $classBreakdown);
        });
    }

    public function captureSlot(string $consumptionId): void
    {
        CampaignEnvelopeConsumption::query()
            ->where('id', $consumptionId)
            ->where('status', CampaignEnvelopeConsumption::STATUS_RESERVED)
            ->update(['status' => CampaignEnvelopeConsumption::STATUS_CAPTURED, 'captured_at' => Carbon::now('UTC')]);
    }

    public function releaseSlot(string $consumptionId): void
    {
        CampaignEnvelopeConsumption::query()
            ->where('id', $consumptionId)
            ->where('status', CampaignEnvelopeConsumption::STATUS_RESERVED)
            ->update(['status' => CampaignEnvelopeConsumption::STATUS_RELEASED]);
    }

    /** @param array<string, mixed>|null $classBreakdown */
    private function toSlot(
        CampaignEnvelopeConsumption $consumption,
        ?Campaign $campaign = null,
        ?array $classBreakdown = null,
    ): CampaignEnvelopeSlot {
        $campaign ??= $consumption->quote()->with('campaignVersion.campaign')->first()?->campaignVersion?->campaign;
        if ($campaign === null) {
            throw new CampaignNotAvailableForDeliveryException;
        }

        $quote = $consumption->quote()->first();
        $breakdown = $quote?->class_breakdown ?? [];
        $classBreakdown ??= (array) ($breakdown[$consumption->economic_class] ?? []);
        $creative = $campaign->currentVersion()?->creative_configuration ?? [];
        $durationMs = (int) ($creative['duration_ms']
            ?? (isset($creative['duration_seconds'])
                ? ((int) $creative['duration_seconds']) * 1000
                : config('campaigns.default_ad_duration_ms')));
        $attentionUnits = max(1, (int) ($creative['attention_units'] ?? $classBreakdown['attention_units'] ?? 1));
        $requiresMediaEnd = ($creative['format'] ?? null) === 'video' && $durationMs > 0;

        return new CampaignEnvelopeSlot(
            consumptionId: $consumption->id,
            organizationId: $campaign->organization_id,
            brandId: $campaign->brand_id,
            objectiveCode: $campaign->objective_code,
            gainMinor: $consumption->gain_minor,
            requiredDurationMs: $durationMs,
            expiresAt: $consumption->expires_at->toIso8601String(),
            attentionUnits: $attentionUnits,
            requiresMediaEnd: $requiresMediaEnd,
        );
    }
}
