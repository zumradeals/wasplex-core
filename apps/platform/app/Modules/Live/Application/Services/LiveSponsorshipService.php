<?php

declare(strict_types=1);

namespace App\Modules\Live\Application\Services;

use App\Modules\AdvertiserWallet\Application\Contracts\AdvertiserLiveBudgetReservationContract;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Live\Infrastructure\Models\LiveAuditEvent;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use App\Modules\Live\Infrastructure\Models\LiveRewardBudgetReservation;
use App\Modules\Live\Infrastructure\Models\LiveRewardCampaign;
use App\Modules\Live\Infrastructure\Models\LiveRewardQuote;
use App\Modules\Subscriptions\Application\Contracts\EconomicClassCatalogContract;
use App\Modules\Subscriptions\Application\ValueObjects\AudienceEstimate;
use App\Shared\Http\AppException;
use Illuminate\Support\Facades\DB;

final class LiveSponsorshipService
{
    public function __construct(
        private readonly EconomicClassCatalogContract $economicClasses,
        private readonly AdvertiserLiveBudgetReservationContract $budgets,
    ) {}

    /**
     * @param  array{country_code?: ?string, economic_classes?: string[]}  $segment
     */
    public function configure(
        LiveEvent $live,
        Account $actor,
        string $advertiserOrganizationId,
        array $segment,
    ): LiveRewardCampaign {
        $this->assertOwner($live, $actor, $advertiserOrganizationId);
        $this->assertEditable($live);

        $activeClassCodes = collect($this->economicClasses->listActive())->pluck('code')->values()->all();
        if ($activeClassCodes === []) {
            throw new AppException(
                'LIVE_SPONSORED_CLASSES_UNAVAILABLE',
                'Aucun niveau membre actif n’est disponible pour le ciblage Live.',
            );
        }

        $requestedClassCodes = collect($segment['economic_classes'] ?? $activeClassCodes)
            ->map(fn (mixed $code): string => strtoupper(trim((string) $code)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($requestedClassCodes === []) {
            $requestedClassCodes = $activeClassCodes;
        }

        $unknownClassCodes = array_values(array_diff($requestedClassCodes, $activeClassCodes));
        if ($unknownClassCodes !== []) {
            throw new AppException(
                'LIVE_SPONSORED_CLASS_INVALID',
                'Un ou plusieurs niveaux membres sélectionnés ne sont pas actifs.',
                ['economic_classes' => $unknownClassCodes],
            );
        }

        $countryCode = $segment['country_code'] ?? null;
        $countryCode = $countryCode === null || trim($countryCode) === ''
            ? null
            : strtoupper(trim($countryCode));

        return DB::transaction(function () use (
            $live,
            $actor,
            $advertiserOrganizationId,
            $requestedClassCodes,
            $countryCode,
        ): LiveRewardCampaign {
            $campaign = LiveRewardCampaign::query()
                ->where('live_id', $live->id)
                ->lockForUpdate()
                ->first();

            if ($campaign?->status === LiveRewardCampaign::STATUS_FUNDS_RESERVED) {
                throw new AppException(
                    'LIVE_SPONSORED_ALREADY_FUNDED',
                    'Le ciblage ne peut plus être modifié après réservation du budget.',
                    [],
                    409,
                );
            }

            $configuration = [
                'territory' => ['country_code' => $countryCode],
                'economic_classes' => $requestedClassCodes,
            ];

            if ($campaign === null) {
                $campaign = LiveRewardCampaign::query()->create([
                    'live_id' => $live->id,
                    'advertiser_organization_id' => $advertiserOrganizationId,
                    'created_by_account_id' => $actor->id,
                    'status' => LiveRewardCampaign::STATUS_DRAFT,
                    'segment_configuration' => $configuration,
                ]);
            } else {
                $campaign->quotes()
                    ->where('status', LiveRewardQuote::STATUS_ACTIVE)
                    ->update(['status' => LiveRewardQuote::STATUS_SUPERSEDED]);
                $campaign->update([
                    'status' => LiveRewardCampaign::STATUS_DRAFT,
                    'segment_configuration' => $configuration,
                ]);
            }

            $live->update([
                'type' => LiveEvent::TYPE_SPONSORED,
                'status' => $live->status === LiveEvent::STATUS_SCHEDULED
                    ? LiveEvent::STATUS_DRAFT
                    : $live->status,
            ]);

            $this->audit($live, $actor->id, 'LiveSponsorshipConfigured', [
                'country_code' => $countryCode,
                'economic_classes' => $requestedClassCodes,
            ]);

            return $campaign->refresh();
        });
    }

    public function estimate(
        LiveEvent $live,
        Account $actor,
        string $advertiserOrganizationId,
    ): AudienceEstimate {
        $campaign = $this->campaignForOwner($live, $actor, $advertiserOrganizationId);
        $segment = $campaign->segment_configuration ?? [];
        $classCodes = $segment['economic_classes'] ?? [];

        if ($classCodes === []) {
            throw new AppException(
                'LIVE_SPONSORED_SEGMENT_REQUIRED',
                'Configurez le ciblage avant d’estimer l’audience.',
            );
        }

        return $this->economicClasses->estimateAudience(
            $classCodes,
            $segment['territory']['country_code'] ?? null,
            (int) config('live.minimum_segment_size'),
        );
    }

    public function quote(
        LiveEvent $live,
        Account $actor,
        string $advertiserOrganizationId,
        int $budgetAmountMinor,
        int $blockDurationSeconds = 300,
    ): LiveRewardQuote {
        $campaign = $this->campaignForOwner($live, $actor, $advertiserOrganizationId);
        $this->assertEditable($live);

        if ($budgetAmountMinor < 2 || $budgetAmountMinor % 2 !== 0) {
            throw new AppException(
                'LIVE_SPONSORED_BUDGET_INVALID',
                'Choisissez un budget positif et divisible par 2 afin de garantir le partage exact 50/50.',
            );
        }

        if (! in_array($blockDurationSeconds, [120, 300, 600], true)) {
            throw new AppException(
                'LIVE_SPONSORED_BLOCK_DURATION_INVALID',
                'Choisissez des blocs de 2, 5 ou 10 minutes.',
            );
        }

        $plannedDurationMinutes = (int) ($live->planned_duration_minutes ?? 0);
        if ($plannedDurationMinutes < 5) {
            throw new AppException(
                'LIVE_SPONSORED_DURATION_REQUIRED',
                'Renseignez la durée prévue du Live avant de générer le devis.',
            );
        }

        return DB::transaction(function () use (
            $campaign,
            $live,
            $actor,
            $budgetAmountMinor,
            $blockDurationSeconds,
            $plannedDurationMinutes,
        ): LiveRewardQuote {
            $lockedCampaign = LiveRewardCampaign::query()->lockForUpdate()->findOrFail($campaign->id);
            if (! in_array($lockedCampaign->status, [
                LiveRewardCampaign::STATUS_DRAFT,
                LiveRewardCampaign::STATUS_QUOTED,
            ], true)) {
                throw new AppException(
                    'LIVE_SPONSORED_NOT_QUOTABLE',
                    'Ce Live sponsorisé ne peut plus recevoir un nouveau devis dans son état actuel.',
                    [],
                    409,
                );
            }

            $segment = $lockedCampaign->segment_configuration ?? [];
            $classCodes = $segment['economic_classes'] ?? [];
            if ($classCodes === []) {
                throw new AppException(
                    'LIVE_SPONSORED_SEGMENT_REQUIRED',
                    'Configurez le ciblage avant de générer le devis.',
                );
            }

            $estimate = $this->economicClasses->estimateAudience(
                $classCodes,
                $segment['territory']['country_code'] ?? null,
                (int) config('live.minimum_segment_size'),
            );
            if ($estimate->tooSmall) {
                throw new AppException(
                    'LIVE_SPONSORED_AUDIENCE_TOO_SMALL',
                    'L’audience estimée est trop petite pour programmer un Live sponsorisé.',
                    [
                        'estimated_reach_min' => $estimate->estimatedMin,
                        'estimated_reach_max' => $estimate->estimatedMax,
                    ],
                );
            }

            $lockedCampaign->quotes()
                ->where('status', LiveRewardQuote::STATUS_ACTIVE)
                ->update(['status' => LiveRewardQuote::STATUS_SUPERSEDED]);

            $spectatorEnvelopeMinor = intdiv($budgetAmountMinor, 2);
            $quote = LiveRewardQuote::query()->create([
                'live_reward_campaign_id' => $lockedCampaign->id,
                'status' => LiveRewardQuote::STATUS_ACTIVE,
                'currency' => 'XOF',
                'budget_amount_minor' => $budgetAmountMinor,
                'wasplex_share_minor' => $spectatorEnvelopeMinor,
                'spectator_envelope_minor' => $spectatorEnvelopeMinor,
                'estimated_reach_min' => $estimate->estimatedMin,
                'estimated_reach_max' => $estimate->estimatedMax,
                'planned_duration_minutes' => $plannedDurationMinutes,
                'block_duration_seconds' => $blockDurationSeconds,
                'quoted_at' => now(),
            ]);

            $lockedCampaign->update(['status' => LiveRewardCampaign::STATUS_QUOTED]);
            $this->audit($live, $actor->id, 'LiveRewardCampaignQuoted', [
                'quote_id' => $quote->id,
                'budget_amount_minor' => $budgetAmountMinor,
                'wasplex_share_minor' => $spectatorEnvelopeMinor,
                'spectator_envelope_minor' => $spectatorEnvelopeMinor,
                'estimated_reach_min' => $estimate->estimatedMin,
                'estimated_reach_max' => $estimate->estimatedMax,
                'block_duration_seconds' => $blockDurationSeconds,
            ]);

            return $quote->refresh();
        });
    }

    public function fund(
        LiveEvent $live,
        Account $actor,
        string $advertiserOrganizationId,
    ): LiveRewardBudgetReservation {
        $campaign = $this->campaignForOwner($live, $actor, $advertiserOrganizationId);
        $this->assertEditable($live);

        return DB::transaction(function () use (
            $campaign,
            $live,
            $actor,
            $advertiserOrganizationId,
        ): LiveRewardBudgetReservation {
            $lockedCampaign = LiveRewardCampaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $existing = $lockedCampaign->budgetReservations()
                ->where('status', LiveRewardBudgetReservation::STATUS_RESERVED)
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            if ($lockedCampaign->status !== LiveRewardCampaign::STATUS_QUOTED) {
                throw new AppException(
                    'LIVE_SPONSORED_QUOTE_REQUIRED',
                    'Générez un devis actif avant de réserver le budget.',
                    [],
                    409,
                );
            }

            $quote = $lockedCampaign->quotes()
                ->where('status', LiveRewardQuote::STATUS_ACTIVE)
                ->latest('quoted_at')
                ->lockForUpdate()
                ->first();
            if ($quote === null) {
                throw new AppException(
                    'LIVE_SPONSORED_QUOTE_REQUIRED',
                    'Aucun devis actif n’est disponible pour ce Live.',
                    [],
                    409,
                );
            }

            $idempotencyKey = "live-budget-reservation:{$quote->id}";
            $ledgerTransactionId = $this->budgets->reserve(
                $advertiserOrganizationId,
                $live->id,
                $quote->budget_amount_minor,
                $idempotencyKey,
            );

            $reservation = LiveRewardBudgetReservation::query()->create([
                'live_reward_campaign_id' => $lockedCampaign->id,
                'live_reward_quote_id' => $quote->id,
                'advertiser_organization_id' => $advertiserOrganizationId,
                'amount_minor' => $quote->budget_amount_minor,
                'status' => LiveRewardBudgetReservation::STATUS_RESERVED,
                'idempotency_key' => $idempotencyKey,
                'ledger_transaction_id' => $ledgerTransactionId,
                'reserved_at' => now(),
            ]);

            $quote->update(['status' => LiveRewardQuote::STATUS_CONSUMED]);
            $lockedCampaign->update(['status' => LiveRewardCampaign::STATUS_FUNDS_RESERVED]);
            $this->audit($live, $actor->id, 'LiveRewardCampaignFunded', [
                'quote_id' => $quote->id,
                'reservation_id' => $reservation->id,
                'amount_minor' => $quote->budget_amount_minor,
                'ledger_transaction_id' => $ledgerTransactionId,
            ]);

            return $reservation->refresh();
        });
    }

    public function cancel(
        LiveEvent $live,
        Account $actor,
        string $advertiserOrganizationId,
    ): LiveEvent {
        $campaign = $this->campaignForOwner($live, $actor, $advertiserOrganizationId);
        $this->assertEditable($live);

        return DB::transaction(function () use ($campaign, $live, $actor, $advertiserOrganizationId): LiveEvent {
            $lockedCampaign = LiveRewardCampaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $reservation = $lockedCampaign->budgetReservations()
                ->where('status', LiveRewardBudgetReservation::STATUS_RESERVED)
                ->lockForUpdate()
                ->first();

            if ($reservation !== null) {
                $releaseTransactionId = $this->budgets->release(
                    $advertiserOrganizationId,
                    $live->id,
                    $reservation->amount_minor,
                    "live-budget-release:{$reservation->id}",
                );
                $reservation->update([
                    'status' => LiveRewardBudgetReservation::STATUS_RELEASED,
                    'released_at' => now(),
                ]);
                $this->audit($live, $actor->id, 'LiveRewardBudgetReleased', [
                    'reservation_id' => $reservation->id,
                    'amount_minor' => $reservation->amount_minor,
                    'ledger_transaction_id' => $releaseTransactionId,
                ]);
            }

            $lockedCampaign->quotes()
                ->where('status', LiveRewardQuote::STATUS_ACTIVE)
                ->update(['status' => LiveRewardQuote::STATUS_SUPERSEDED]);
            $lockedCampaign->update(['status' => LiveRewardCampaign::STATUS_CANCELLED]);
            $live->update([
                'type' => LiveEvent::TYPE_STANDARD,
                'status' => $live->scheduled_at?->isFuture() === true
                    ? LiveEvent::STATUS_SCHEDULED
                    : LiveEvent::STATUS_DRAFT,
            ]);
            $this->audit($live, $actor->id, 'LiveSponsorshipCancelled');

            return $live->refresh();
        });
    }

    public function assertCanManage(
        LiveEvent $live,
        Account $actor,
        string $advertiserOrganizationId,
    ): void {
        $this->assertOwner($live, $actor, $advertiserOrganizationId);
    }

    public function assertMayScheduleOrStart(
        LiveEvent $live,
        Account $actor,
        string $advertiserOrganizationId,
    ): void {
        $this->assertOwner($live, $actor, $advertiserOrganizationId);

        if ($live->type !== LiveEvent::TYPE_SPONSORED) {
            return;
        }

        $campaign = LiveRewardCampaign::query()->where('live_id', $live->id)->first();
        $hasReservedBudget = $campaign?->budgetReservations()
            ->where('status', LiveRewardBudgetReservation::STATUS_RESERVED)
            ->exists() ?? false;
        if ($campaign?->status !== LiveRewardCampaign::STATUS_FUNDS_RESERVED || ! $hasReservedBudget) {
            throw new AppException(
                'LIVE_SPONSORED_FUNDING_REQUIRED',
                'Réservez le budget du Live sponsorisé avant sa programmation officielle ou son démarrage.',
                [],
                409,
            );
        }
    }

    private function campaignForOwner(
        LiveEvent $live,
        Account $actor,
        string $advertiserOrganizationId,
    ): LiveRewardCampaign {
        $this->assertOwner($live, $actor, $advertiserOrganizationId);

        $campaign = LiveRewardCampaign::query()
            ->where('live_id', $live->id)
            ->where('advertiser_organization_id', $advertiserOrganizationId)
            ->first();

        if ($campaign === null) {
            throw new AppException(
                'LIVE_SPONSORED_NOT_CONFIGURED',
                'Activez d’abord la sponsorisation de ce Live.',
                [],
                409,
            );
        }

        return $campaign;
    }

    private function assertEditable(LiveEvent $live): void
    {
        if (! in_array($live->status, [LiveEvent::STATUS_DRAFT, LiveEvent::STATUS_SCHEDULED], true)) {
            throw new AppException(
                'LIVE_SPONSORED_NOT_EDITABLE',
                'La sponsorisation ne peut plus être modifiée une fois le Live démarré.',
                [],
                409,
            );
        }
    }

    private function assertOwner(LiveEvent $live, Account $actor, string $advertiserOrganizationId): void
    {
        if ((string) $live->advertiser_organization_id !== $advertiserOrganizationId) {
            throw new AppException(
                'LIVE_ADVERTISER_CONTEXT_MISMATCH',
                'Ce Live n’appartient pas à l’espace annonceur actif.',
                [],
                403,
            );
        }

        if ((string) $live->owner_account_id !== (string) $actor->id) {
            throw new AppException(
                'LIVE_OWNER_REQUIRED',
                'Seul le créateur de ce Live peut gérer sa sponsorisation.',
                [],
                403,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function audit(LiveEvent $live, ?string $actorAccountId, string $eventType, array $metadata = []): void
    {
        LiveAuditEvent::query()->create([
            'live_id' => $live->id,
            'actor_account_id' => $actorAccountId,
            'event_type' => $eventType,
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }
}
