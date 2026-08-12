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
 * l'autre moitié revient à Wasplex au fil des vues complètes. Les classes
 * d'abonnement restent internes : le devis couvre automatiquement tous les
 * niveaux actifs, sans choix de l'annonceur.
 */
final class CampaignQuoteService
{
    public function __construct(private readonly EconomicClassCatalogContract $economicClasses) {}

    public function quote(CampaignVersion $version): CampaignQuote
    {
        $audience = $version->audience_configuration ?? [];
        $budget = $version->budget_configuration ?? [];
        $creative = $version->creative_configuration ?? [];
        $attentionUnits = max(1, (int) ($creative['attention_units'] ?? 1));
        $durationMs = (int) ($creative['duration_ms'] ?? config('campaigns.default_ad_duration_ms'));
        $countryCode = $audience['territory']['country_code'] ?? null;
        $dailyBudgetMinor = (int) ($budget['daily_budget_minor'] ?? 0);
        $configuredDurationDays = (int) ($budget['duration_days'] ?? 0);
        $grossAmountMinor = $dailyBudgetMinor > 0 && $configuredDurationDays > 0
            ? $dailyBudgetMinor * $configuredDurationDays
            : (int) ($budget['budget_amount_minor'] ?? 0);

        if ($grossAmountMinor <= 0) {
            throw new InvalidCampaignConfigurationException(
                'Choisissez un budget quotidien et une durée.'
            );
        }

        $classes = collect($this->economicClasses->listActive())->values();
        $classCodes = $classes->pluck('code')->all();

        if ($classCodes === []) {
            throw new InvalidCampaignConfigurationException(
                'Aucun niveau membre actif n’est disponible pour la diffusion.'
            );
        }

        $priceVersion = AdvertisingPriceVersion::query()
            ->where('status', AdvertisingPriceVersion::STATUS_PUBLISHED)
            ->orderByDesc('published_at')
            ->first();

        if ($priceVersion === null) {
            throw new NoPublishedPriceCatalogException;
        }

        // Existing drafts created before flexible durations keep working
        // with the published default; new drafts persist the advertiser's
        // explicit choice in budget_configuration.
        $durationDays = (int) ($budget['duration_days'] ?? $priceVersion->duration_days);
        $dailyBudgetMinor = $dailyBudgetMinor > 0 ? $dailyBudgetMinor : intdiv($grossAmountMinor, $durationDays);

        if ($grossAmountMinor < $priceVersion->minimum_budget_minor) {
            throw new InvalidCampaignConfigurationException(
                "Le budget total minimum est de {$priceVersion->minimum_budget_minor} FCFA."
            );
        }

        if ($durationDays < $priceVersion->minimum_duration_days || $durationDays > $priceVersion->maximum_duration_days) {
            throw new InvalidCampaignConfigurationException(
                "Choisissez une durée comprise entre {$priceVersion->minimum_duration_days} et {$priceVersion->maximum_duration_days} jours."
            );
        }

        if ($dailyBudgetMinor < $priceVersion->minimum_daily_budget_minor) {
            throw new InvalidCampaignConfigurationException(
                "Prévoyez au moins {$priceVersion->minimum_daily_budget_minor} FCFA par jour de diffusion."
            );
        }

        $minimumSegmentSize = (int) config('campaigns.minimum_segment_size');
        $estimate = $this->economicClasses->estimateAudience($classCodes, $countryCode, $minimumSegmentSize);

        $netDistributableAmountMinor = $grossAmountMinor;
        $userEnvelopeTotal = intdiv($netDistributableAmountMinor, 2);
        $classBreakdown = [];
        $highestReward = 0;

        foreach ($classes as $summary) {
            $baseRewardMinor = $summary->rewardPerCompleteViewMinor;
            $gainUnitaireMinor = $baseRewardMinor * $attentionUnits;
            $events = intdiv($userEnvelopeTotal, $gainUnitaireMinor);
            $highestReward = max($highestReward, $gainUnitaireMinor);

            $classBreakdown[$summary->code] = [
                'envelope_minor' => $userEnvelopeTotal,
                'events' => $events,
                'gain_unitaire_minor' => $gainUnitaireMinor,
                'base_reward_minor' => $baseRewardMinor,
                'attention_units' => $attentionUnits,
                'duration_ms' => $durationMs,
            ];
        }

        // Estimation prudente : si toutes les vues appartenaient au niveau
        // actif le mieux rémunéré, ce nombre reste finançable.
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

            $budgetConfiguration = $version->budget_configuration ?? [];
            $budgetConfiguration['daily_budget_minor'] = intdiv($grossAmountMinor, max(1, (int) ($budgetConfiguration['duration_days'] ?? $priceVersion->duration_days)));
            $budgetConfiguration['budget_amount_minor'] = $grossAmountMinor;

            $version->update([
                'price_version_id' => $priceVersion->id,
                'budget_configuration' => $budgetConfiguration,
                'status' => CampaignVersion::STATUS_QUOTED,
            ]);
            $version->campaign()->update(['status' => Campaign::STATUS_QUOTED]);

            return $quote;
        });
    }
}
