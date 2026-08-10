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
 * Partage fixe 50/50 : la moitié du budget finance les récompenses et
 * l'autre moitié revient à Wasplex au fil des vues complètes. Le gain est
 * une valeur WP explicite du niveau d'abonnement ; aucun poids,
 * coefficient ou multiplicateur média n'intervient dans le calcul.
 */
final class CampaignQuoteService
{
    public function __construct(private readonly EconomicClassCatalogContract $economicClasses) {}

    public function quote(CampaignVersion $version): CampaignQuote
    {
        $audience = $version->audience_configuration ?? [];
        $budget = $version->budget_configuration ?? [];
        $classCodes = $audience['economic_classes'] ?? [];
        $countryCode = $audience['territory']['country_code'] ?? null;
        $grossAmountMinor = (int) ($budget['budget_amount_minor'] ?? 0);

        if ($classCodes === [] || $grossAmountMinor <= 0) {
            throw new InvalidCampaignConfigurationException(
                "L'audience (au moins une classe) et un budget positif sont requis avant de générer un devis."
            );
        }

        $priceVersion = AdvertisingPriceVersion::query()
            ->where('status', AdvertisingPriceVersion::STATUS_PUBLISHED)
            ->orderByDesc('published_at')
            ->first();

        if ($priceVersion === null) {
            throw new NoPublishedPriceCatalogException;
        }

        if ($grossAmountMinor < $priceVersion->minimum_budget_minor) {
            throw new InvalidCampaignConfigurationException(
                "Le budget minimum pour {$priceVersion->duration_days} jours est de {$priceVersion->minimum_budget_minor} FCFA."
            );
        }

        $minimumSegmentSize = (int) config('campaigns.minimum_segment_size');
        $estimate = $this->economicClasses->estimateAudience($classCodes, $countryCode, $minimumSegmentSize);

        if ($estimate->tooSmall) {
            throw new SegmentTooSmallException($estimate->total(), $minimumSegmentSize);
        }

        $classes = collect($this->economicClasses->listActive())
            ->filter(fn ($summary) => in_array($summary->code, $classCodes, true))
            ->values();

        $netDistributableAmountMinor = $grossAmountMinor;
        $userEnvelopeTotal = intdiv($netDistributableAmountMinor, 2);
        $classBreakdown = [];
        $highestReward = 0;

        foreach ($classes as $summary) {
            $gainUnitaireMinor = $summary->rewardPerCompleteViewMinor;
            $events = intdiv($userEnvelopeTotal, $gainUnitaireMinor);
            $highestReward = max($highestReward, $gainUnitaireMinor);

            $classBreakdown[$summary->code] = [
                'envelope_minor' => $userEnvelopeTotal,
                'events' => $events,
                'gain_unitaire_minor' => $gainUnitaireMinor,
            ];
        }

        // Estimation prudente : si toutes les vues appartenaient au niveau
        // ciblé le mieux rémunéré, ce nombre reste finançable.
        $estimatedEvents = $highestReward > 0 ? intdiv($userEnvelopeTotal, $highestReward) : 0;

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
}
