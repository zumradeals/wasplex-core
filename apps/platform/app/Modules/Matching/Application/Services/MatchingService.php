<?php

declare(strict_types=1);

namespace App\Modules\Matching\Application\Services;

use App\Modules\Campaigns\Application\Contracts\ApprovedCampaignAudienceContract;
use App\Modules\Campaigns\Application\ValueObjects\ApprovedCampaignAudience;
use App\Modules\Identity\Application\Contracts\AccountCountryLookupContract;
use App\Modules\Matching\Application\Contracts\MatchingContract;
use App\Modules\Matching\Application\ValueObjects\MatchingDecisionResult;
use App\Modules\Matching\Infrastructure\Models\MatchingConfiguration;
use App\Modules\Matching\Infrastructure\Models\MatchingDecision;
use App\Modules\SmartProfile\Application\Contracts\AdvertisingConsentContract;
use App\Modules\SmartProfile\Infrastructure\Models\ConsentPurpose;
use App\Modules\Subscriptions\Application\Contracts\EconomicClassCatalogContract;
use Illuminate\Support\Carbon;

/**
 * docs/04-moteur-matching-et-distribution-publicitaire-wasplex.md §15,
 * réduit au périmètre réel de ce dépôt (docs/chantiers/P008-CHANTIER.md
 * §8) : campagne approuvée et non suspendue → période (si renseignée) →
 * territoire → classe économique → consentement publicitaire. Un refus de
 * consentement est une exclusion dure (`ineligible`) ; l'absence de
 * décision est un doute de confidentialité (`withheld`), jamais assimilée
 * à un refus.
 */
final class MatchingService implements MatchingContract
{
    public function __construct(
        private readonly ApprovedCampaignAudienceContract $campaigns,
        private readonly EconomicClassCatalogContract $economicClasses,
        private readonly AccountCountryLookupContract $countries,
        private readonly AdvertisingConsentContract $consents,
    ) {}

    public function checkEligibility(string $campaignId, string $accountId): MatchingDecisionResult
    {
        $campaign = $this->campaigns->find($campaignId);

        if ($campaign === null) {
            return $this->decide($campaignId, null, $accountId, MatchingDecision::DECISION_INELIGIBLE, ['campaign_not_approved']);
        }

        $today = Carbon::now('UTC')->toDateString();

        if ($campaign->scheduledStart !== null && $today < $campaign->scheduledStart) {
            return $this->decide($campaignId, $campaign->campaignVersionId, $accountId, MatchingDecision::DECISION_INELIGIBLE, ['campaign_not_started']);
        }

        if ($campaign->scheduledEnd !== null && $today > $campaign->scheduledEnd) {
            return $this->decide($campaignId, $campaign->campaignVersionId, $accountId, MatchingDecision::DECISION_INELIGIBLE, ['campaign_ended']);
        }

        $territoryReason = $this->territoryMismatch($campaign, $accountId);

        if ($territoryReason !== null) {
            return $this->decide($campaignId, $campaign->campaignVersionId, $accountId, MatchingDecision::DECISION_INELIGIBLE, [$territoryReason]);
        }

        $classReason = $this->classMismatch($campaign, $accountId);

        if ($classReason !== null) {
            return $this->decide($campaignId, $campaign->campaignVersionId, $accountId, MatchingDecision::DECISION_INELIGIBLE, [$classReason]);
        }

        $consentState = $this->consents->stateFor($accountId, ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION);

        if ($consentState === AdvertisingConsentContract::STATE_REFUSED) {
            return $this->decide($campaignId, $campaign->campaignVersionId, $accountId, MatchingDecision::DECISION_INELIGIBLE, ['consent_denied']);
        }

        if ($consentState === AdvertisingConsentContract::STATE_NOT_DECIDED) {
            return $this->decide($campaignId, $campaign->campaignVersionId, $accountId, MatchingDecision::DECISION_WITHHELD, ['consent_not_decided']);
        }

        return $this->decide($campaignId, $campaign->campaignVersionId, $accountId, MatchingDecision::DECISION_ELIGIBLE, ['territory_match', 'class_match', 'consent_active']);
    }

    public function explain(string $campaignId, string $accountId): array
    {
        $result = $this->checkEligibility($campaignId, $accountId);

        if (! $result->isEligible()) {
            return [];
        }

        $labels = [
            'territory_match' => 'Votre pays correspond à la zone visée par cette campagne.',
            'class_match' => 'Votre classe économique correspond à l\'audience visée.',
            'consent_active' => 'Vous avez autorisé la personnalisation publicitaire.',
        ];

        return array_values(array_filter(array_map(fn (string $code) => $labels[$code] ?? null, $result->reasonCodes)));
    }

    private function territoryMismatch(ApprovedCampaignAudience $campaign, string $accountId): ?string
    {
        $targetedCountry = $campaign->audienceConfiguration['territory']['country_code'] ?? null;

        if ($targetedCountry === null) {
            return null;
        }

        $accountCountry = $this->countries->countryForAccount($accountId);

        return $accountCountry === $targetedCountry ? null : 'territory_mismatch';
    }

    private function classMismatch(ApprovedCampaignAudience $campaign, string $accountId): ?string
    {
        $targetedClasses = $campaign->audienceConfiguration['economic_classes'] ?? [];

        if ($targetedClasses === []) {
            return null;
        }

        $accountClass = $this->economicClasses->classForAccount($accountId);

        return $accountClass !== null && in_array($accountClass, $targetedClasses, true) ? null : 'class_mismatch';
    }

    /**
     * @param  string[]  $reasonCodes
     */
    private function decide(string $campaignId, ?string $campaignVersionId, string $accountId, string $decision, array $reasonCodes): MatchingDecisionResult
    {
        $decidedAt = Carbon::now('UTC');

        $configurationId = MatchingConfiguration::query()
            ->where('status', MatchingConfiguration::STATUS_PUBLISHED)
            ->orderByDesc('published_at')
            ->value('id');

        MatchingDecision::query()->updateOrCreate(
            ['campaign_id' => $campaignId, 'account_id' => $accountId],
            [
                'campaign_version_id' => $campaignVersionId ?? '',
                'matching_configuration_id' => $configurationId,
                'decision' => $decision,
                'reason_codes' => $reasonCodes,
                'decided_at' => $decidedAt,
            ],
        );

        return new MatchingDecisionResult($campaignId, $decision, $reasonCodes, $decidedAt->toIso8601String());
    }
}
