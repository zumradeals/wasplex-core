<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use App\Modules\Campaigns\Infrastructure\Models\AdvertisingPriceVersion;
use App\Modules\Campaigns\Infrastructure\Models\Campaign;
use App\Modules\Campaigns\Infrastructure\Models\CampaignQuote;
use App\Modules\Campaigns\Infrastructure\Models\CampaignVersion;
use App\Modules\Subscriptions\Application\Contracts\EconomicClassCatalogContract;
use Illuminate\Support\Facades\DB;

/**
 * docs/05-modele-economique-publicitaire-wasplex.md §4-19 : partage
 * 50/50, répartition normalisée par classe, gain unitaire, reliquat
 * conservé, arrondis exacts (1 WP = 1 FCFA, jamais de fraction cachée).
 * docs/chantiers/P006-CHANTIER.md §3.2 : seuls le format et la classe
 * ciblée sont des axes de prix réels dans ce chantier.
 */
final class CampaignQuoteService
{
    public function __construct(private readonly EconomicClassCatalogContract $economicClasses) {}

    public function quote(CampaignVersion $version): CampaignQuote
    {
        $audience = $version->audience_configuration ?? [];
        $budget = $version->budget_configuration ?? [];
        $creative = $version->creative_configuration ?? [];

        $classCodes = $audience['economic_classes'] ?? [];
        $countryCode = $audience['territory']['country_code'] ?? null;
        $grossAmountMinor = (int) ($budget['budget_amount_minor'] ?? 0);
        $format = $creative['format'] ?? 'image';

        if ($classCodes === [] || $grossAmountMinor <= 0) {
            throw new InvalidCampaignConfigurationException(
                "L'audience (au moins une classe) et un budget positif sont requis avant de générer un devis."
            );
        }

        $priceVersion = AdvertisingPriceVersion::query()
            ->where('status', AdvertisingPriceVersion::STATUS_PUBLISHED)
            ->orderByDesc('published_at')
            ->first();

        if ($priceVersion === null || $priceVersion->base_price_minor_per_event <= 0) {
            throw new NoPublishedPriceCatalogException;
        }

        $minimumSegmentSize = (int) config('campaigns.minimum_segment_size');
        $estimate = $this->economicClasses->estimateAudience($classCodes, $countryCode, $minimumSegmentSize);

        if ($estimate->tooSmall) {
            throw new SegmentTooSmallException($estimate->total(), $minimumSegmentSize);
        }

        $classes = collect($this->economicClasses->listActive())
            ->filter(fn ($summary) => in_array($summary->code, $classCodes, true))
            ->values();

        $totalWeight = $classes->sum('weightPercent');
        $netDistributableAmountMinor = $grossAmountMinor;
        $userEnvelopeTotal = intdiv($netDistributableAmountMinor, 2);

        $envelopeByClass = $this->apportion(
            $userEnvelopeTotal,
            $classes->mapWithKeys(fn ($summary) => [$summary->code => (float) $summary->weightPercent / (float) $totalWeight])->all(),
        );

        $formatMultiplier = $priceVersion->multiplierFor($format);
        $classBreakdown = [];
        $estimatedEvents = 0;

        foreach ($classes as $summary) {
            $envelopeMinor = $envelopeByClass[$summary->code];
            $costPerEventMinor = (int) ceil($priceVersion->base_price_minor_per_event * $formatMultiplier * $summary->coefficient);

            $events = $costPerEventMinor > 0 ? intdiv($envelopeMinor, $costPerEventMinor) : 0;
            $gainUnitaireMinor = $events > 0 ? intdiv($envelopeMinor, $events) : 0;
            $reliquatMinor = $envelopeMinor - ($gainUnitaireMinor * $events);

            $classBreakdown[$summary->code] = [
                'weight_percent_normalized' => round(((float) $summary->weightPercent / (float) $totalWeight) * 100, 4),
                'envelope_minor' => $envelopeMinor,
                'cost_per_event_minor' => $costPerEventMinor,
                'events' => $events,
                'gain_unitaire_minor' => $gainUnitaireMinor,
                'reliquat_minor' => $reliquatMinor,
            ];

            $estimatedEvents += $events;
        }

        return DB::transaction(function () use ($version, $priceVersion, $netDistributableAmountMinor, $grossAmountMinor, $estimatedEvents, $estimate, $classBreakdown): CampaignQuote {
            $quote = CampaignQuote::query()->create([
                'campaign_version_id' => $version->id,
                'currency' => $priceVersion->currency,
                'gross_amount_minor' => $grossAmountMinor,
                'net_distributable_amount_minor' => $netDistributableAmountMinor,
                'estimated_events' => $estimatedEvents,
                'estimated_reach_min' => $estimate->estimatedMin,
                'estimated_reach_max' => $estimate->estimatedMax,
                'class_breakdown' => $classBreakdown,
                'expires_at' => now()->addHours((int) config('campaigns.quote_validity_hours')),
                'price_version_id' => $priceVersion->id,
                'status' => CampaignQuote::STATUS_ACTIVE,
            ]);

            $version->update(['price_version_id' => $priceVersion->id, 'status' => CampaignVersion::STATUS_QUOTED]);
            $version->campaign()->update(['status' => Campaign::STATUS_QUOTED]);

            return $quote;
        });
    }

    /**
     * Largest-remainder apportionment: distributes $total across the given
     * normalized weights so the parts sum back to $total exactly — no
     * fraction is ever silently lost (docs/05 §18).
     *
     * @param  array<string, float>  $normalizedWeights
     * @return array<string, int>
     */
    private function apportion(int $total, array $normalizedWeights): array
    {
        $floors = [];
        $remainders = [];

        foreach ($normalizedWeights as $code => $weight) {
            $raw = $total * $weight;
            $floors[$code] = (int) floor($raw);
            $remainders[$code] = $raw - floor($raw);
        }

        $remaining = $total - array_sum($floors);

        arsort($remainders);

        foreach (array_keys($remainders) as $code) {
            if ($remaining <= 0) {
                break;
            }

            $floors[$code]++;
            $remaining--;
        }

        return $floors;
    }
}
