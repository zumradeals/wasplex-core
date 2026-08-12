<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use App\Modules\AdvertiserStudio\Application\Contracts\BrandDirectoryContract;
use App\Modules\AdvertiserStudio\Application\Contracts\CreativeAssetDirectoryContract;
use App\Modules\AdvertiserWallet\Application\Contracts\AdvertiserWalletReservationContract;
use App\Modules\Campaigns\Infrastructure\Models\Campaign;
use App\Modules\Campaigns\Infrastructure\Models\CampaignBudgetReservation;
use App\Modules\Campaigns\Infrastructure\Models\CampaignQuote;
use App\Modules\Campaigns\Infrastructure\Models\CampaignReviewCase;
use App\Modules\Campaigns\Infrastructure\Models\CampaignReviewEvent;
use App\Modules\Campaigns\Infrastructure\Models\CampaignVersion;
use App\Modules\Subscriptions\Application\Contracts\EconomicClassCatalogContract;
use App\Modules\Subscriptions\Application\ValueObjects\AudienceEstimate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Assistant simplifié avec autosave : chaque appel à updateVersion()
 * persiste immédiatement l'état courant du brouillon.
 */
final class CampaignService
{
    public function __construct(
        private readonly BrandDirectoryContract $brands,
        private readonly CreativeAssetDirectoryContract $creativeAssets,
        private readonly EconomicClassCatalogContract $economicClasses,
        private readonly AdvertiserWalletReservationContract $reservations,
        private readonly CampaignQuoteService $quoteService,
        private readonly AudienceConfigurationValidator $audienceValidator,
    ) {}

    public function list(string $organizationId): Collection
    {
        return Campaign::query()
            ->where('organization_id', $organizationId)
            ->with('versions')
            ->orderByDesc('created_at')
            ->get();
    }

    public function create(string $organizationId, string $brandId, string $createdBy): Campaign
    {
        if ($this->brands->find($organizationId, $brandId) === null) {
            throw new CampaignBrandNotFoundException($brandId);
        }

        return DB::transaction(function () use ($organizationId, $brandId, $createdBy): Campaign {
            $campaign = Campaign::query()->create([
                'organization_id' => $organizationId,
                'brand_id' => $brandId,
                'type' => Campaign::TYPE_FAST,
                'status' => Campaign::STATUS_DRAFT,
                'currency' => 'XOF',
                'created_by' => $createdBy,
            ]);

            CampaignVersion::query()->create([
                'campaign_id' => $campaign->id,
                'version_number' => 1,
                'status' => CampaignVersion::STATUS_DRAFT,
            ]);

            return $campaign->load('versions');
        });
    }

    public function find(string $organizationId, string $campaignId): Campaign
    {
        $campaign = Campaign::query()
            ->where('organization_id', $organizationId)
            ->with(['versions.quotes.priceVersion', 'reviewCases' => fn ($query) => $query->orderByDesc('opened_at')])
            ->find($campaignId);

        if ($campaign === null) {
            throw new CampaignNotFoundException($campaignId);
        }

        return $campaign;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateVersion(string $organizationId, string $campaignId, array $data): Campaign
    {
        $campaign = $this->find($organizationId, $campaignId);

        $locked = [Campaign::STATUS_SUBMITTED, Campaign::STATUS_APPROVED, Campaign::STATUS_REJECTED, Campaign::STATUS_SUSPENDED];

        if (in_array($campaign->status, $locked, true)) {
            throw new InvalidCampaignStateException('Cette campagne ne peut plus être modifiée dans son état actuel.');
        }

        $version = $campaign->currentVersion();

        if (isset($data['objective_code']) && ! array_key_exists($data['objective_code'], Campaign::OBJECTIVES)) {
            throw new InvalidCampaignConfigurationException("Objectif inconnu : {$data['objective_code']}.");
        }

        $update = [];

        if (array_key_exists('objective_code', $data)) {
            $update['objective_code'] = $data['objective_code'];
        }

        if (isset($data['creative_configuration'])) {
            $update['creative_configuration'] = $this->validateCreativeConfiguration($organizationId, $data['creative_configuration']);
        }

        if (isset($data['audience_configuration'])) {
            $audienceConfiguration = $data['audience_configuration'];
            // Compatibility with old clients/drafts: a class list may still
            // arrive over the wire, but it is discarded before validation
            // and persistence. The advertiser cannot influence it.
            unset($audienceConfiguration['economic_classes']);
            $this->audienceValidator->validate($audienceConfiguration);
            $update['audience_configuration'] = $audienceConfiguration;
        }

        if (isset($data['budget_configuration'])) {
            $update['budget_configuration'] = $this->validateBudgetConfiguration($data['budget_configuration']);
        }

        return DB::transaction(function () use ($campaign, $version, $update): Campaign {
            if (array_key_exists('objective_code', $update)) {
                $campaign->update(['objective_code' => $update['objective_code']]);
            }

            $versionUpdate = array_intersect_key($update, array_flip(['creative_configuration', 'audience_configuration', 'budget_configuration']));

            if ($versionUpdate !== [] || $version->status !== CampaignVersion::STATUS_DRAFT) {
                $versionUpdate['status'] = CampaignVersion::STATUS_DRAFT;
                $version->update($versionUpdate);
            }

            if (! in_array($campaign->status, [Campaign::STATUS_DRAFT, Campaign::STATUS_CHANGES_REQUESTED], true)) {
                $campaign->update(['status' => Campaign::STATUS_DRAFT]);
            }

            return $campaign->refresh();
        });
    }

    public function estimateAudience(string $organizationId, string $campaignId): AudienceEstimate
    {
        $campaign = $this->find($organizationId, $campaignId);
        $audience = $campaign->currentVersion()->audience_configuration ?? [];
        $classCodes = collect($this->economicClasses->listActive())->pluck('code')->all();

        if ($classCodes === []) {
            throw new InvalidCampaignConfigurationException('Aucun niveau membre actif n’est disponible pour estimer l’audience.');
        }

        return $this->economicClasses->estimateAudience(
            $classCodes,
            $audience['territory']['country_code'] ?? null,
            (int) config('campaigns.minimum_segment_size'),
        );
    }

    public function quote(string $organizationId, string $campaignId): Campaign
    {
        $campaign = $this->find($organizationId, $campaignId);

        if (! in_array($campaign->status, [Campaign::STATUS_DRAFT, Campaign::STATUS_QUOTED], true)) {
            throw new InvalidCampaignStateException('Un devis ne peut être généré que pour une campagne en brouillon ou déjà devisée.');
        }

        $version = $campaign->currentVersion();
        $creative = $version->creative_configuration ?? [];
        if (empty($creative['asset_id'])) {
            throw new InvalidCampaignConfigurationException('Ajoute une vidéo avant de générer le devis.');
        }
        $version->update([
            'creative_configuration' => $this->validateCreativeConfiguration($organizationId, $creative),
        ]);

        $this->quoteService->quote($version->refresh());

        return $this->find($organizationId, $campaignId);
    }

    public function fund(string $organizationId, string $campaignId): Campaign
    {
        $campaign = $this->find($organizationId, $campaignId);

        $existing = CampaignBudgetReservation::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', CampaignBudgetReservation::STATUS_RESERVED)
            ->first();

        if ($existing !== null) {
            return $campaign;
        }

        if ($campaign->status !== Campaign::STATUS_QUOTED) {
            throw new InvalidCampaignStateException('La campagne doit être devisée avant financement.');
        }

        $version = $campaign->currentVersion();
        $quote = $version->latestQuote();

        if ($quote === null || $quote->status !== CampaignQuote::STATUS_ACTIVE) {
            throw new InvalidCampaignStateException('Aucun devis actif — générez un devis avant de financer.');
        }

        if ($quote->isExpired()) {
            throw new QuoteExpiredException;
        }

        $idempotencyKey = "campaign-budget-reservation:{$quote->id}";

        try {
            return DB::transaction(function () use ($campaign, $version, $quote, $idempotencyKey, $organizationId): Campaign {
                $ledgerTransactionId = $this->reservations->reserve(
                    $organizationId,
                    $campaign->id,
                    $quote->gross_amount_minor,
                    $idempotencyKey,
                );

                CampaignBudgetReservation::query()->create([
                    'campaign_id' => $campaign->id,
                    'campaign_quote_id' => $quote->id,
                    'organization_id' => $organizationId,
                    'amount_minor' => $quote->gross_amount_minor,
                    'status' => CampaignBudgetReservation::STATUS_RESERVED,
                    'idempotency_key' => $idempotencyKey,
                    'ledger_transaction_id' => $ledgerTransactionId,
                ]);

                $quote->update(['status' => CampaignQuote::STATUS_CONSUMED]);
                $version->update(['status' => CampaignVersion::STATUS_FUNDED]);
                $campaign->update(['status' => Campaign::STATUS_FUNDED]);

                return $campaign->refresh();
            });
        } catch (QueryException $exception) {
            if (! Str::contains($exception->getMessage(), 'idempotency_key')) {
                throw $exception;
            }

            return $this->find($organizationId, $campaignId);
        }
    }

    public function submit(string $organizationId, string $campaignId, string $actorAccountId): Campaign
    {
        $campaign = $this->find($organizationId, $campaignId);

        if ($campaign->status !== Campaign::STATUS_FUNDED) {
            throw new InvalidCampaignStateException('La campagne doit être financée avant soumission.');
        }

        return DB::transaction(fn (): Campaign => $this->openReviewCase($campaign, $actorAccountId, CampaignReviewEvent::TYPE_SUBMITTED));
    }

    public function financeAndSubmit(string $organizationId, string $campaignId, string $actorAccountId): Campaign
    {
        $campaign = $this->find($organizationId, $campaignId);

        $version = $campaign->currentVersion();
        if ($campaign->objective_code === null || empty($version->creative_configuration['asset_id'])) {
            throw new InvalidCampaignConfigurationException(
                'Choisis un objectif et une vidéo avant d’envoyer la campagne.'
            );
        }

        if ($campaign->status === Campaign::STATUS_DRAFT) {
            $campaign = $this->quote($organizationId, $campaignId);
        }

        if ($campaign->status === Campaign::STATUS_QUOTED) {
            $campaign = $this->fund($organizationId, $campaignId);
        }

        if ($campaign->status !== Campaign::STATUS_FUNDED) {
            throw new InvalidCampaignStateException('Cette campagne ne peut pas être financée et envoyée dans son état actuel.');
        }

        return $this->submit($organizationId, $campaignId, $actorAccountId);
    }

    public function resubmit(string $organizationId, string $campaignId, string $actorAccountId): Campaign
    {
        $campaign = $this->find($organizationId, $campaignId);

        if ($campaign->status !== Campaign::STATUS_CHANGES_REQUESTED) {
            throw new InvalidCampaignStateException('Seule une campagne avec correction demandée peut être resoumise.');
        }

        return DB::transaction(fn (): Campaign => $this->openReviewCase($campaign, $actorAccountId, CampaignReviewEvent::TYPE_RESUBMITTED));
    }

    private function openReviewCase(Campaign $campaign, string $actorAccountId, string $eventType): Campaign
    {
        $version = $campaign->currentVersion();
        $version->update(['status' => CampaignVersion::STATUS_SUBMITTED]);
        $campaign->update(['status' => Campaign::STATUS_SUBMITTED]);

        $case = CampaignReviewCase::query()->create([
            'campaign_id' => $campaign->id,
            'campaign_version_id' => $version->id,
            'status' => CampaignReviewCase::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        CampaignReviewEvent::query()->create([
            'campaign_review_case_id' => $case->id,
            'event_type' => $eventType,
            'actor_account_id' => $actorAccountId,
        ]);

        return $campaign->refresh();
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @return array<string, mixed>
     */
    private function validateCreativeConfiguration(string $organizationId, array $configuration): array
    {
        if (! isset($configuration['asset_id'])) {
            return $configuration;
        }

        $asset = $this->creativeAssets->find($organizationId, $configuration['asset_id']);

        if ($asset === null) {
            throw new InvalidCampaignConfigurationException("Média introuvable : {$configuration['asset_id']}.");
        }

        if ($asset->type !== 'video') {
            throw new InvalidCampaignConfigurationException('Wasplex V1 diffuse uniquement des publicités vidéo.');
        }

        $durationMs = $asset->durationMs ?? (($asset->duration ?? 0) * 1000);
        $maxDurationMs = ((int) config('advertiser_studio.video.max_duration_seconds')) * 1000;

        if ($durationMs <= 0 || $durationMs > $maxDurationMs) {
            throw new InvalidCampaignConfigurationException(
                'La vidéo doit avoir une durée vérifiée comprise entre 1 seconde et 5 minutes.'
            );
        }

        $unitMs = max(1, (int) config('campaigns.attention_unit_ms'));
        $configuration['format'] = 'video';
        $configuration['duration_ms'] = $durationMs;
        $configuration['attention_units'] = (int) ceil($durationMs / $unitMs);
        unset($configuration['duration_seconds']);

        return $configuration;
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @return array<string, mixed>
     */
    private function validateBudgetConfiguration(array $configuration): array
    {
        if (isset($configuration['budget_amount_minor']) && (int) $configuration['budget_amount_minor'] <= 0) {
            throw new InvalidCampaignConfigurationException('Le budget doit être un montant positif.');
        }

        if (isset($configuration['duration_days']) && (int) $configuration['duration_days'] <= 0) {
            throw new InvalidCampaignConfigurationException('La durée doit être exprimée en jours et être positive.');
        }

        if (isset($configuration['daily_budget_minor']) && (int) $configuration['daily_budget_minor'] <= 0) {
            throw new InvalidCampaignConfigurationException('Le budget quotidien doit être positif.');
        }

        if (isset($configuration['daily_budget_minor'], $configuration['duration_days'])) {
            $configuration['budget_amount_minor'] = (int) $configuration['daily_budget_minor'] * (int) $configuration['duration_days'];
        }

        return $configuration;
    }
}
