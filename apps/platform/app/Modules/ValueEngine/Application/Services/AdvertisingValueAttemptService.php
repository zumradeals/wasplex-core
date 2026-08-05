<?php

namespace App\Modules\ValueEngine\Application\Services;

use App\Modules\Advertising\Application\Services\EconomicClassEligibilityProjection;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingMatch;
use App\Modules\Campaign\Infrastructure\Models\Campaign;
use App\Modules\Campaign\Infrastructure\Models\CampaignBudgetReservation;
use App\Modules\Campaign\Infrastructure\Models\CampaignFunding;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use App\Modules\ValueEngine\Application\Data\AdvertisingValueQuote;
use App\Modules\ValueEngine\Application\Data\StartedValueAttempt;
use App\Modules\ValueEngine\Domain\Enums\AttentionSessionStatus;
use App\Modules\ValueEngine\Domain\Enums\ValueAttemptStatus;
use App\Modules\ValueEngine\Domain\Exceptions\ValueEngineException;
use App\Modules\ValueEngine\Infrastructure\Models\AttentionSession;
use App\Modules\ValueEngine\Infrastructure\Models\ValueAttempt;
use App\Modules\ValueEngine\Infrastructure\Models\ValueCampaignBudgetCounter;
use App\Modules\Wallet\Application\Services\WalletCatalog;
use App\Modules\Wallet\Domain\Enums\ReservationStatus;
use Illuminate\Support\Facades\DB;

final readonly class AdvertisingValueAttemptService
{
    public function __construct(
        private ValueEngineCatalog $catalog,
        private AdvertisingValueCalculator $calculator,
        private EconomicClassEligibilityProjection $economicClasses,
        private WalletCatalog $wallets,
        private AttentionTokenSigner $tokens,
        private ValueOutbox $outbox,
    ) {}

    public function start(
        Account $account,
        Campaign $campaign,
        AdvertisingMatch $match,
        string $deviceSessionId,
        string $idempotencyKey,
        ?string $countryCode = null,
    ): StartedValueAttempt {
        $this->guardStartInput($account, $campaign, $match, $deviceSessionId, $idempotencyKey);
        $this->catalog->ensureAdvertisingCore();

        $existing = ValueAttempt::query()
            ->where('idempotency_key', $idempotencyKey)
            ->with(['attentionSession', 'rule'])
            ->first();
        if ($existing instanceof ValueAttempt) {
            return $this->startedResult($existing, $deviceSessionId);
        }

        $userSpace = $account->spaces()
            ->where('kind', SpaceKind::User->value)
            ->whereNull('organization_id')
            ->first();
        if (! $userSpace instanceof UserSpace) {
            throw new ValueEngineException('Le compte ne possède pas d’espace utilisateur rémunérable.');
        }

        $userWallet = $this->wallets->forSpace($userSpace);
        $economicClass = $this->economicClasses->code($account);
        $market = strtoupper($countryCode ?? (string) config('profile_intelligence.default_market'));
        $campaign->loadMissing([
            'activeVersion',
            'funding.quote',
            'funding.wallet',
            'funding.budgetReservation.walletReservation',
        ]);
        $funding = $campaign->funding;
        if (! $funding instanceof CampaignFunding) {
            throw new ValueEngineException('La campagne ne possède pas de financement et de devis utilisables.');
        }

        $rule = $this->catalog->resolveAdvertisingRule(
            economicClass: $economicClass,
            countryCode: $market,
            currency: $funding->quote->currency,
        );
        $quote = $this->calculator->quote($funding->quote, $rule);
        $attentionToken = $this->tokens->sign($idempotencyKey, $account->id, $deviceSessionId);

        return DB::transaction(function () use (
            $account,
            $campaign,
            $match,
            $deviceSessionId,
            $idempotencyKey,
            $market,
            $economicClass,
            $userWallet,
            $funding,
            $rule,
            $quote,
            $attentionToken,
        ): StartedValueAttempt {
            $lockedFunding = CampaignFunding::query()
                ->whereKey($funding->id)
                ->with(['quote', 'wallet', 'budgetReservation.walletReservation'])
                ->lockForUpdate()
                ->firstOrFail();
            $existing = ValueAttempt::query()
                ->where('idempotency_key', $idempotencyKey)
                ->with(['attentionSession', 'rule'])
                ->first();
            if ($existing instanceof ValueAttempt) {
                return $this->startedResult($existing, $deviceSessionId);
            }

            $this->guardFunding($lockedFunding);
            $counter = ValueCampaignBudgetCounter::query()
                ->where('campaign_funding_id', $lockedFunding->id)
                ->lockForUpdate()
                ->first();
            if (! $counter instanceof ValueCampaignBudgetCounter) {
                ValueCampaignBudgetCounter::query()->create([
                    'campaign_funding_id' => $lockedFunding->id,
                    'limit_amount_minor' => $lockedFunding->amount_minor,
                    'reserved_amount_minor' => 0,
                    'settled_amount_minor' => 0,
                    'released_amount_minor' => 0,
                    'version' => 0,
                ]);
                $counter = ValueCampaignBudgetCounter::query()
                    ->where('campaign_funding_id', $lockedFunding->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            if ($counter->availableAmountMinor() < $quote->grossAmountMinor) {
                throw new ValueEngineException('Le budget disponible de la campagne ne finance plus ce gain.');
            }

            $activeVersionId = $campaign->active_version_id;
            if ($activeVersionId === null || $match->campaign_version_id !== $activeVersionId) {
                throw new ValueEngineException('Le Matching ne correspond plus à la version active de la campagne.');
            }

            $advertiserWallet = $lockedFunding->wallet;

            $attempt = ValueAttempt::query()->create([
                'value_event_definition_id' => $this->catalog->advertisingEvent()->id,
                'value_rule_version_id' => $rule->id,
                'account_id' => $account->id,
                'campaign_id' => $campaign->id,
                'campaign_version_id' => $activeVersionId,
                'advertising_match_id' => $match->id,
                'user_wallet_id' => $userWallet->id,
                'advertiser_wallet_id' => $advertiserWallet->id,
                'campaign_funding_id' => $lockedFunding->id,
                'source_event_id' => 'feed-delivery:'.$idempotencyKey,
                'idempotency_key' => $idempotencyKey,
                'status' => ValueAttemptStatus::Reserved,
                'economic_class' => $economicClass,
                'country_code' => $market,
                'unit' => $quote->unit,
                'currency' => $quote->currency,
                'gross_amount_minor' => $quote->grossAmountMinor,
                'user_amount_minor' => $quote->userAmountMinor,
                'wasplex_amount_minor' => $quote->wasplexAmountMinor,
                'calculation_hash' => $quote->calculationHash,
                'reserved_at' => now(),
                'expires_at' => now()->addSeconds($rule->attempt_ttl_seconds),
                'metadata' => [
                    'advertiser_identity_exported' => false,
                    'user_identity_exported' => false,
                    'financial_operation_created' => false,
                    'gain_displayable' => true,
                ],
            ]);
            $session = AttentionSession::query()->create([
                'value_attempt_id' => $attempt->id,
                'account_id' => $account->id,
                'device_session_id' => $deviceSessionId,
                'status' => AttentionSessionStatus::Created,
                'required_duration_ms' => $rule->required_attention_ms,
                'visible_duration_ms' => 0,
                'last_sequence' => 0,
                'token_hash' => hash('sha256', $attentionToken),
                'metadata' => [
                    'heartbeat_interval_ms' => $rule->heartbeat_interval_ms,
                    'client_may_credit' => false,
                ],
            ]);

            $counter->forceFill([
                'reserved_amount_minor' => $counter->reserved_amount_minor + $quote->grossAmountMinor,
                'version' => $counter->version + 1,
            ])->save();
            $this->outbox->record(
                'value_attempt',
                $attempt->id,
                'VALUE_ATTEMPT_RESERVED',
                'value-attempt-reserved:'.$attempt->id,
                [
                    'value_attempt_id' => $attempt->id,
                    'campaign_id' => $campaign->id,
                    'gross_amount_minor' => $quote->grossAmountMinor,
                    'user_amount_minor' => $quote->userAmountMinor,
                    'wasplex_amount_minor' => $quote->wasplexAmountMinor,
                    'currency' => $quote->currency,
                    'rule_version_id' => $rule->id,
                ],
            );
            DB::afterCommit(static fn () => event('value-engine.attempt-reserved', [$attempt->id]));

            return new StartedValueAttempt($attempt, $session, $quote, $attentionToken);
        }, 5);
    }

    public function release(
        ValueAttempt $attempt,
        Account $account,
        string $reason,
        ValueAttemptStatus $status = ValueAttemptStatus::Released,
    ): ValueAttempt {
        if (! in_array($status, [
            ValueAttemptStatus::Released,
            ValueAttemptStatus::Expired,
            ValueAttemptStatus::Rejected,
        ], true)) {
            throw new ValueEngineException('Ce statut ne peut pas libérer une tentative.');
        }

        return DB::transaction(function () use ($attempt, $account, $reason, $status): ValueAttempt {
            $locked = ValueAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $this->guardOwnership($locked, $account);

            return $this->releaseLocked($locked, $status, $reason);
        }, 5);
    }

    public function expireDue(): int
    {
        $ids = ValueAttempt::query()
            ->whereIn('status', [
                ValueAttemptStatus::Reserved->value,
                ValueAttemptStatus::AttentionActive->value,
            ])
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->pluck('id');

        $expired = 0;
        foreach ($ids as $id) {
            DB::transaction(function () use ($id): void {
                $attempt = ValueAttempt::query()->whereKey($id)->lockForUpdate()->firstOrFail();
                if (! $attempt->status->isFinal()) {
                    $this->releaseLocked($attempt, ValueAttemptStatus::Expired, 'ATTENTION_SESSION_EXPIRED');
                }
            }, 5);
            $expired++;
        }

        return $expired;
    }

    public function releaseLocked(
        ValueAttempt $attempt,
        ValueAttemptStatus $status,
        string $reason,
    ): ValueAttempt {
        if ($attempt->status === ValueAttemptStatus::Completed || $attempt->status->isFinal()) {
            return $attempt;
        }

        $counter = ValueCampaignBudgetCounter::query()
            ->where('campaign_funding_id', $attempt->campaign_funding_id)
            ->lockForUpdate()
            ->firstOrFail();
        $counter->forceFill([
            'reserved_amount_minor' => max(0, $counter->reserved_amount_minor - $attempt->gross_amount_minor),
            'released_amount_minor' => $counter->released_amount_minor + $attempt->gross_amount_minor,
            'version' => $counter->version + 1,
        ])->save();

        $attempt->forceFill([
            'status' => $status,
            'released_at' => now(),
            'release_reason' => trim($reason),
        ])->save();
        $session = AttentionSession::query()
            ->where('value_attempt_id', $attempt->id)
            ->lockForUpdate()
            ->first();
        if ($session instanceof AttentionSession && ! $session->status->isFinal()) {
            $session->forceFill([
                'status' => match ($status) {
                    ValueAttemptStatus::Expired => AttentionSessionStatus::Expired,
                    ValueAttemptStatus::Rejected => AttentionSessionStatus::Rejected,
                    default => AttentionSessionStatus::Abandoned,
                },
                'rejected_at' => now(),
            ])->save();
        }

        $this->outbox->record(
            'value_attempt',
            $attempt->id,
            'VALUE_ATTEMPT_RELEASED',
            'value-attempt-released:'.$attempt->id,
            [
                'value_attempt_id' => $attempt->id,
                'campaign_id' => $attempt->campaign_id,
                'status' => $status->value,
                'reason' => trim($reason),
                'financial_operation_created' => false,
            ],
        );
        DB::afterCommit(static fn () => event('value-engine.attempt-released', [
            $attempt->id,
            $status->value,
        ]));

        return $attempt->refresh();
    }

    public function guardOwnership(ValueAttempt $attempt, Account $account): void
    {
        if ($attempt->account_id !== $account->id) {
            throw new ValueEngineException('Cette tentative ne correspond pas au compte authentifié.');
        }
    }

    private function guardStartInput(
        Account $account,
        Campaign $campaign,
        AdvertisingMatch $match,
        string $deviceSessionId,
        string $idempotencyKey,
    ): void {
        if ($campaign->status !== 'approved') {
            throw new ValueEngineException('Seule une campagne approuvée peut être livrée.');
        }
        if (
            $match->account_id !== $account->id
            || $match->campaign_id !== $campaign->id
            || $match->decision !== 'eligible'
        ) {
            throw new ValueEngineException('Le Matching ne permet pas cette livraison.');
        }
        if (trim($deviceSessionId) === '' || trim($idempotencyKey) === '') {
            throw new ValueEngineException('La session appareil et la clé d’idempotence sont obligatoires.');
        }
    }

    private function guardFunding(CampaignFunding $funding): void
    {
        $budget = $funding->budgetReservation;
        if (
            $funding->status !== 'approved'
            || ! $budget instanceof CampaignBudgetReservation
            || $budget->status !== 'active'
            || $budget->walletReservation === null
            || $budget->walletReservation->status !== ReservationStatus::Active
        ) {
            throw new ValueEngineException('La réservation financière de la campagne n’est pas active.');
        }
    }

    private function startedResult(ValueAttempt $attempt, string $deviceSessionId): StartedValueAttempt
    {
        $session = $attempt->attentionSession;
        if (! $session instanceof AttentionSession) {
            throw new ValueEngineException('La tentative existante ne possède pas de session d’attention.');
        }
        if ($session->device_session_id !== $deviceSessionId) {
            throw new ValueEngineException('Cette tentative appartient déjà à une autre session appareil.');
        }

        return new StartedValueAttempt(
            attempt: $attempt,
            attentionSession: $session,
            quote: new AdvertisingValueQuote(
                grossAmountMinor: $attempt->gross_amount_minor,
                userAmountMinor: $attempt->user_amount_minor,
                wasplexAmountMinor: $attempt->wasplex_amount_minor,
                unit: $attempt->unit,
                currency: $attempt->currency,
                ruleVersionId: $attempt->value_rule_version_id,
                calculationHash: $attempt->calculation_hash,
            ),
            attentionToken: $this->tokens->sign(
                $attempt->idempotency_key,
                $attempt->account_id,
                $deviceSessionId,
            ),
        );
    }
}
