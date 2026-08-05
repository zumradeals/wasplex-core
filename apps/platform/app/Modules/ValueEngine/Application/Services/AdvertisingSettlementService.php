<?php

namespace App\Modules\ValueEngine\Application\Services;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Ledger\Application\Contracts\LedgerContract;
use App\Modules\Ledger\Application\Data\PostingEntry;
use App\Modules\Ledger\Application\Data\PostingRequest;
use App\Modules\Ledger\Domain\Enums\EntryDirection;
use App\Modules\ValueEngine\Domain\Enums\AttentionSessionStatus;
use App\Modules\ValueEngine\Domain\Enums\ValueAttemptStatus;
use App\Modules\ValueEngine\Domain\Exceptions\ValueEngineException;
use App\Modules\ValueEngine\Infrastructure\Models\AttentionSession;
use App\Modules\ValueEngine\Infrastructure\Models\ValueAttempt;
use App\Modules\ValueEngine\Infrastructure\Models\ValueCampaignBudgetCounter;
use App\Modules\Wallet\Application\Services\WalletProjector;
use App\Modules\Wallet\Infrastructure\Models\Wallet;
use App\Modules\Wallet\Infrastructure\Models\WalletBalanceProjection;
use App\Modules\Wallet\Infrastructure\Models\WalletOperation;
use Illuminate\Support\Facades\DB;

final readonly class AdvertisingSettlementService
{
    public function __construct(
        private ValueEngineCatalog $catalog,
        private LedgerContract $ledger,
        private WalletProjector $projector,
        private ValueOutbox $outbox,
    ) {}

    public function settle(
        ValueAttempt $attempt,
        Account $account,
        string $idempotencyKey,
        ?string $traceId = null,
    ): ValueAttempt {
        if (trim($idempotencyKey) === '') {
            throw new ValueEngineException('La clé d’idempotence du règlement est obligatoire.');
        }

        return DB::transaction(function () use ($attempt, $account, $idempotencyKey, $traceId): ValueAttempt {
            $locked = ValueAttempt::query()
                ->whereKey($attempt->id)
                ->with(['attentionSession', 'rule'])
                ->lockForUpdate()
                ->firstOrFail();
            if ($locked->account_id !== $account->id) {
                throw new ValueEngineException('Cette tentative ne correspond pas au compte authentifié.');
            }
            if ($locked->status === ValueAttemptStatus::Completed) {
                return $locked;
            }

            $session = $locked->attentionSession;
            if (
                ! $session instanceof AttentionSession
                || $session->status !== AttentionSessionStatus::Completed
                || $session->visible_duration_ms < $session->required_duration_ms
                || $locked->status !== ValueAttemptStatus::ProofPending
            ) {
                throw new ValueEngineException('La preuve d’attention qualifiée n’est pas validée.');
            }

            $counter = ValueCampaignBudgetCounter::query()
                ->where('campaign_funding_id', $locked->campaign_funding_id)
                ->lockForUpdate()
                ->firstOrFail();
            $advertiserWallet = Wallet::query()
                ->whereKey($locked->advertiser_wallet_id)
                ->lockForUpdate()
                ->firstOrFail();
            $userWallet = Wallet::query()
                ->whereKey($locked->user_wallet_id)
                ->lockForUpdate()
                ->firstOrFail();
            $advertiserProjection = WalletBalanceProjection::query()
                ->where('wallet_id', $advertiserWallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $advertiserWallet->reserved_ledger_account_id === null
                || $userWallet->available_ledger_account_id === null
                || $counter->reserved_amount_minor < $locked->gross_amount_minor
                || $advertiserProjection->reserved_minor < $locked->gross_amount_minor
            ) {
                throw new ValueEngineException('La valeur réservée de la campagne n’est plus disponible.');
            }

            $posted = $this->ledger->post(new PostingRequest(
                journalCode: 'VALUE_ENGINE',
                type: 'AD_QUALIFIED_ATTENTION_SETTLEMENT',
                sourceModule: 'value-engine',
                businessReference: 'value-attempt:'.$locked->id,
                idempotencyKey: 'value-settlement:'.$idempotencyKey,
                unit: $locked->unit,
                currency: $locked->currency,
                entries: [
                    new PostingEntry(
                        $advertiserWallet->reserved_ledger_account_id,
                        EntryDirection::Debit,
                        $locked->gross_amount_minor,
                        'Consommation de campagne après attention qualifiée',
                    ),
                    new PostingEntry(
                        $userWallet->available_ledger_account_id,
                        EntryDirection::Credit,
                        $locked->user_amount_minor,
                        'Crédit automatique du gain publicitaire',
                    ),
                    new PostingEntry(
                        $this->catalog->revenueAccountId(),
                        EntryDirection::Credit,
                        $locked->wasplex_amount_minor,
                        'Part Wasplex de la diffusion qualifiée',
                    ),
                ],
                actorAccountId: null,
                beneficiaryAccountId: $account->id,
                justification: 'Preuve d’attention publicitaire validée par le Super Moteur',
                ruleVersion: $locked->rule->code.':v'.$locked->rule->version,
                metadata: [
                    'value_attempt_id' => $locked->id,
                    'campaign_id' => $locked->campaign_id,
                    'advertising_match_id' => $locked->advertising_match_id,
                    'calculation_hash' => $locked->calculation_hash,
                    'client_created_value' => false,
                ],
                traceId: $traceId,
            ));

            $counter->forceFill([
                'reserved_amount_minor' => max(0, $counter->reserved_amount_minor - $locked->gross_amount_minor),
                'settled_amount_minor' => $counter->settled_amount_minor + $locked->gross_amount_minor,
                'version' => $counter->version + 1,
            ])->save();
            $locked->forceFill([
                'status' => ValueAttemptStatus::Completed,
                'ledger_transaction_id' => $posted->transactionId,
                'settled_at' => now(),
                'metadata' => [
                    'advertiser_identity_exported' => false,
                    'user_identity_exported' => false,
                    'financial_operation_created' => true,
                    'gain_displayable' => true,
                    'server_confirmed' => true,
                ],
            ])->save();

            $this->walletOperation(
                $advertiserWallet,
                $posted->transactionId,
                'advertising_value_settlement',
                'debit',
                $locked->gross_amount_minor,
                'advertising-debit:'.$locked->id,
                'Budget consommé après attention qualifiée',
                $locked->id,
            );
            $this->walletOperation(
                $userWallet,
                $posted->transactionId,
                'advertising_reward',
                'credit',
                $locked->user_amount_minor,
                'advertising-credit:'.$locked->id,
                'Gain publicitaire crédité automatiquement',
                $locked->id,
            );
            $this->projector->rebuild($advertiserWallet, $posted->transactionId);
            $this->projector->rebuild($userWallet, $posted->transactionId);

            $this->outbox->record(
                'value_attempt',
                $locked->id,
                'ADVERTISING_VALUE_SETTLED',
                'advertising-value-settled:'.$locked->id,
                [
                    'value_attempt_id' => $locked->id,
                    'campaign_id' => $locked->campaign_id,
                    'ledger_transaction_id' => $posted->transactionId,
                    'user_wallet_id' => $userWallet->id,
                    'user_amount_minor' => $locked->user_amount_minor,
                    'wasplex_amount_minor' => $locked->wasplex_amount_minor,
                    'currency' => $locked->currency,
                    'server_confirmed' => true,
                ],
            );
            DB::afterCommit(static fn () => event('value-engine.advertising-settled', [
                $locked->id,
                $userWallet->id,
                $locked->user_amount_minor,
            ]));

            return $locked->refresh();
        }, 5);
    }

    private function walletOperation(
        Wallet $wallet,
        string $ledgerTransactionId,
        string $type,
        string $direction,
        int $amountMinor,
        string $idempotencyKey,
        string $description,
        string $valueAttemptId,
    ): void {
        WalletOperation::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'wallet_id' => $wallet->id,
                'ledger_transaction_id' => $ledgerTransactionId,
                'type' => $type,
                'direction' => $direction,
                'status' => 'posted',
                'amount_minor' => $amountMinor,
                'unit' => $wallet->unit,
                'currency' => $wallet->currency,
                'business_reference' => 'value-attempt:'.$valueAttemptId,
                'description' => $description,
                'metadata' => ['value_attempt_id' => $valueAttemptId],
                'occurred_at' => now(),
            ],
        );
    }
}
