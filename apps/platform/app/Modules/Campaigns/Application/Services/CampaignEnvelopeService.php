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

/**
 * docs/07-super-moteur-unifie-valeur-temps-reel-wasplex.md §15 :
 * "Sans réservation confirmée, aucune publicité rémunérée ne commence."
 * Tracks capacity per (devis, classe économique) only — anonymous, never
 * knows which account holds a slot (docs/chantiers/P009-CHANTIER.md §3).
 */
final class CampaignEnvelopeService implements CampaignEnvelopeContract
{
    public function reserveSlot(string $campaignId, string $economicClass, string $idempotencyKey): CampaignEnvelopeSlot
    {
        $existing = CampaignEnvelopeConsumption::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            return $this->toSlot($existing);
        }

        $campaign = Campaign::query()->where('status', Campaign::STATUS_APPROVED)->find($campaignId);

        if ($campaign === null) {
            throw new CampaignNotAvailableForDeliveryException;
        }

        $quote = $campaign->currentVersion()?->latestQuote();

        // A campaign can only reach `approved` after fund() — which
        // transitions its quote from `active` to `consumed` (docs/chantiers/
        // P006-CHANTIER.md: funding "spends" the devis into a real
        // reservation). `consumed` is therefore the expected, deliverable
        // state here, not a stale one.
        if ($quote === null || $quote->status !== CampaignQuote::STATUS_CONSUMED) {
            throw new CampaignNotAvailableForDeliveryException;
        }

        $classBreakdown = $quote->class_breakdown[$economicClass] ?? null;

        if ($classBreakdown === null) {
            throw new CampaignNotAvailableForDeliveryException;
        }

        return DB::transaction(function () use ($campaign, $quote, $economicClass, $classBreakdown, $idempotencyKey): CampaignEnvelopeSlot {
            // Lock the quote row itself to serialize concurrent
            // reservations against the same envelope — the consumption
            // rows being counted may not exist yet, so they can't be the
            // lock target, and PostgreSQL rejects `FOR UPDATE` combined
            // with an aggregate (count()) anyway.
            CampaignQuote::query()->whereKey($quote->id)->lockForUpdate()->first();

            $consumed = CampaignEnvelopeConsumption::query()
                ->where('campaign_quote_id', $quote->id)
                ->where('economic_class', $economicClass)
                ->whereIn('status', [CampaignEnvelopeConsumption::STATUS_RESERVED, CampaignEnvelopeConsumption::STATUS_CAPTURED])
                ->count();

            if ($consumed >= (int) $classBreakdown['events']) {
                throw new CampaignEnvelopeExhaustedException;
            }

            $now = Carbon::now('UTC');

            $consumption = CampaignEnvelopeConsumption::query()->create([
                'campaign_quote_id' => $quote->id,
                'economic_class' => $economicClass,
                'status' => CampaignEnvelopeConsumption::STATUS_RESERVED,
                'gain_minor' => (int) $classBreakdown['gain_unitaire_minor'],
                'reserved_at' => $now,
                'expires_at' => $now->clone()->addSeconds((int) config('campaigns.envelope_reservation_ttl_seconds')),
                'idempotency_key' => $idempotencyKey,
            ]);

            return $this->toSlot($consumption, $campaign);
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

    private function toSlot(CampaignEnvelopeConsumption $consumption, ?Campaign $campaign = null): CampaignEnvelopeSlot
    {
        $campaign ??= $consumption->quote()->with('campaignVersion.campaign')->first()?->campaignVersion?->campaign;

        $creative = $campaign->currentVersion()?->creative_configuration ?? [];
        $durationMs = isset($creative['duration_seconds'])
            ? ((int) $creative['duration_seconds']) * 1000
            : (int) config('campaigns.default_ad_duration_ms');

        return new CampaignEnvelopeSlot(
            consumptionId: $consumption->id,
            organizationId: $campaign->organization_id,
            brandId: $campaign->brand_id,
            objectiveCode: $campaign->objective_code,
            gainMinor: $consumption->gain_minor,
            requiredDurationMs: $durationMs,
            expiresAt: $consumption->expires_at->toIso8601String(),
        );
    }
}
