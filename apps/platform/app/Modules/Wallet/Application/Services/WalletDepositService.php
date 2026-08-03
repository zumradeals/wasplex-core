<?php

namespace App\Modules\Wallet\Application\Services;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use App\Modules\Ledger\Application\Contracts\LedgerContract;
use App\Modules\Ledger\Application\Data\PostingEntry;
use App\Modules\Ledger\Application\Data\PostingRequest;
use App\Modules\Ledger\Domain\Enums\EntryDirection;
use App\Modules\Wallet\Application\Contracts\PaymentGatewayContract;
use App\Modules\Wallet\Application\Data\CreateGatewayPayment;
use App\Modules\Wallet\Application\Data\GatewayPayment;
use App\Modules\Wallet\Domain\Enums\DepositStatus;
use App\Modules\Wallet\Domain\Enums\WalletStatus;
use App\Modules\Wallet\Domain\Events\DepositCreated;
use App\Modules\Wallet\Domain\Events\DepositCredited;
use App\Modules\Wallet\Domain\Events\DepositFailed;
use App\Modules\Wallet\Domain\Events\DepositPaymentConfirmed;
use App\Modules\Wallet\Domain\Exceptions\PaymentGatewayException;
use App\Modules\Wallet\Domain\Exceptions\WalletException;
use App\Modules\Wallet\Infrastructure\Models\Wallet;
use App\Modules\Wallet\Infrastructure\Models\WalletDeposit;
use App\Modules\Wallet\Infrastructure\Models\WalletOperation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class WalletDepositService
{
    public function __construct(
        private PaymentGatewayContract $gateway,
        private LedgerContract $ledger,
        private WalletCatalog $catalog,
        private WalletProjector $projector,
        private WalletAuditor $auditor,
    ) {}

    public function create(
        Wallet $wallet,
        int $amountMinor,
        string $idempotencyKey,
        string $successUrl,
        string $errorUrl,
        Account $actor,
        UserSpace $space,
        ?string $traceId = null,
    ): WalletDeposit {
        $minimum = (int) config('services.geniuspay.minimum_deposit_minor', 200);
        $maximum = (int) config('services.geniuspay.maximum_deposit_minor', 5000000);

        if ($wallet->status !== WalletStatus::Active) {
            throw new WalletException('Ce Wallet ne peut pas recevoir de dépôt.');
        }

        if ($amountMinor < $minimum || $amountMinor > $maximum) {
            throw new WalletException("Le montant doit être compris entre {$minimum} et {$maximum} FCFA.");
        }

        $deposit = DB::transaction(function () use ($wallet, $amountMinor, $idempotencyKey): WalletDeposit {
            return WalletDeposit::query()->firstOrCreate(
                ['wallet_id' => $wallet->id, 'idempotency_key' => $idempotencyKey],
                [
                    'provider' => 'geniuspay',
                    'amount_minor' => $amountMinor,
                    'currency' => $wallet->currency,
                    'status' => DepositStatus::CreatingPayment,
                ],
            );
        });

        if (! $deposit->wasRecentlyCreated) {
            if ($deposit->amount_minor !== $amountMinor || strtoupper($deposit->currency) !== strtoupper($wallet->currency)) {
                throw new WalletException('Cette clé d’idempotence appartient déjà à un autre montant.');
            }

            return $deposit;
        }

        DB::afterCommit(static fn () => event(new DepositCreated($deposit->id, $wallet->id, $amountMinor)));

        try {
            $payment = $this->gateway->createPayment(new CreateGatewayPayment(
                depositId: $deposit->id,
                amountMinor: $amountMinor,
                currency: $wallet->currency,
                description: "Dépôt Wallet Wasplex {$deposit->id}",
                successUrl: $successUrl,
                errorUrl: $errorUrl,
                metadata: [
                    'deposit_id' => $deposit->id,
                    'wallet_id' => $wallet->id,
                ],
            ));

            $this->assertPaymentMatches($deposit, $payment);

            DB::transaction(function () use ($deposit, $payment): void {
                $locked = WalletDeposit::query()->lockForUpdate()->findOrFail($deposit->id);
                $locked->forceFill([
                    'provider_payment_id' => $payment->id,
                    'provider_reference' => $payment->reference,
                    'provider_status' => $payment->status,
                    'fee_minor' => $payment->feeMinor,
                    'status' => $payment->succeeded() ? DepositStatus::PaymentDetected : DepositStatus::AwaitingPayment,
                    'checkout_url' => $payment->checkoutUrl,
                    'provider_metadata' => [
                        'environment' => $payment->environment,
                        'fee_minor' => $payment->feeMinor,
                    ],
                    'expires_at' => $payment->expiresAt,
                ])->save();
            });
        } catch (Throwable $exception) {
            WalletDeposit::query()->whereKey($deposit->id)->update([
                'status' => DepositStatus::Unknown->value,
                'provider_status' => 'creation_unknown',
                'updated_at' => now(),
            ]);
            Log::warning('wallet.deposit.creation_unknown', [
                'deposit_id' => $deposit->id,
                'exception' => $exception::class,
            ]);
            $this->auditor->record(
                action: 'wallet.deposit.creation_unknown', outcome: 'warning', wallet: $wallet,
                actor: $actor, space: $space, subjectType: 'wallet_deposit', subjectId: $deposit->id,
                traceId: $traceId,
            );

            if ($exception instanceof PaymentGatewayException || $exception instanceof WalletException) {
                throw $exception;
            }

            throw new PaymentGatewayException('Le paiement sandbox n’a pas pu être initialisé. Réessaie plus tard.', previous: $exception);
        }

        $this->auditor->record(
            action: 'wallet.deposit.created', outcome: 'success', wallet: $wallet,
            actor: $actor, space: $space, subjectType: 'wallet_deposit', subjectId: $deposit->id,
            traceId: $traceId, context: ['amount_minor' => $amountMinor, 'provider' => 'geniuspay'],
        );

        $deposit->refresh();
        if ($payment->succeeded()) {
            return $this->synchronizePayment($payment);
        }

        return $deposit;
    }

    public function synchronizePayment(GatewayPayment $payment): WalletDeposit
    {
        $deposit = WalletDeposit::query()
            ->with('wallet')
            ->where('provider', 'geniuspay')
            ->where('provider_reference', $payment->reference)
            ->first();

        if ($deposit === null) {
            throw new WalletException('Aucun dépôt Wasplex ne correspond à cette référence GeniusPay.');
        }

        $this->assertPaymentMatches($deposit, $payment);

        if (! $payment->succeeded()) {
            return $this->recordNonSuccessfulStatus($deposit, $payment);
        }

        DB::transaction(function () use ($deposit, $payment): void {
            $locked = WalletDeposit::query()->lockForUpdate()->findOrFail($deposit->id);
            if ($locked->status === DepositStatus::Credited) {
                return;
            }

            $locked->forceFill([
                'provider_payment_id' => $payment->id,
                'provider_status' => $payment->status,
                'fee_minor' => $payment->feeMinor,
                'status' => DepositStatus::Confirmed,
                'confirmed_at' => $locked->confirmed_at ?? now(),
            ])->save();

            DB::afterCommit(static fn () => event(new DepositPaymentConfirmed($locked->id, $payment->reference)));
        });

        $deposit->refresh();
        if ($deposit->status === DepositStatus::Credited) {
            return $deposit;
        }

        $wallet = $deposit->wallet;
        if ($wallet->available_ledger_account_id === null) {
            throw new WalletException('Le Wallet du dépôt est incomplet.');
        }

        $posted = $this->ledger->post(new PostingRequest(
            journalCode: 'WALLET',
            type: 'DEPOSIT',
            sourceModule: 'wallet',
            businessReference: "wallet_deposit:{$deposit->id}",
            idempotencyKey: "wallet-deposit:{$deposit->id}",
            unit: $wallet->unit,
            currency: $wallet->currency,
            entries: [
                new PostingEntry(
                    accountId: $this->catalog->clearingAccountId(),
                    direction: EntryDirection::Debit,
                    amountMinor: $deposit->amount_minor,
                    description: 'Fonds GeniusPay sandbox reçus',
                    externalReference: $payment->reference,
                ),
                new PostingEntry(
                    accountId: $wallet->available_ledger_account_id,
                    direction: EntryDirection::Credit,
                    amountMinor: $deposit->amount_minor,
                    description: 'Crédit du Wallet annonceur',
                    externalReference: $payment->reference,
                ),
            ],
            beneficiaryAccountId: $wallet->account_id,
            justification: 'Paiement GeniusPay sandbox revérifié côté serveur.',
            ruleVersion: 'p003-deposit-v1',
            metadata: [
                'deposit_id' => $deposit->id,
                'provider' => 'geniuspay',
                'provider_reference' => $payment->reference,
                'environment' => 'sandbox',
                'fee_minor' => $payment->feeMinor,
            ],
        ));

        DB::transaction(function () use ($deposit, $wallet, $posted, $payment): void {
            $locked = WalletDeposit::query()->lockForUpdate()->findOrFail($deposit->id);
            $locked->forceFill([
                'ledger_transaction_id' => $posted->transactionId,
                'provider_status' => $payment->status,
                'status' => DepositStatus::Credited,
                'credited_at' => $locked->credited_at ?? now(),
            ])->save();

            WalletOperation::query()->firstOrCreate(
                ['ledger_transaction_id' => $posted->transactionId],
                [
                    'wallet_id' => $wallet->id,
                    'type' => 'deposit',
                    'direction' => 'credit',
                    'status' => 'posted',
                    'amount_minor' => $deposit->amount_minor,
                    'unit' => $wallet->unit,
                    'currency' => $wallet->currency,
                    'business_reference' => "wallet_deposit:{$deposit->id}",
                    'idempotency_key' => "wallet-deposit:{$deposit->id}",
                    'description' => 'Dépôt GeniusPay sandbox',
                    'metadata' => ['deposit_id' => $deposit->id, 'provider_reference' => $payment->reference],
                    'occurred_at' => now(),
                ],
            );

            DB::afterCommit(static fn () => event(new DepositCredited(
                $deposit->id, $wallet->id, $posted->transactionId, $deposit->amount_minor,
            )));
        });

        $this->projector->rebuild($wallet, $posted->transactionId);
        $this->auditor->record(
            action: 'wallet.deposit.credited', outcome: 'success', wallet: $wallet,
            subjectType: 'wallet_deposit', subjectId: $deposit->id,
            context: ['amount_minor' => $deposit->amount_minor, 'provider_reference' => $payment->reference],
        );

        return $deposit->fresh() ?? $deposit;
    }

    private function recordNonSuccessfulStatus(WalletDeposit $deposit, GatewayPayment $payment): WalletDeposit
    {
        $status = match ($payment->status) {
            'failed' => DepositStatus::Failed,
            'cancelled', 'canceled' => DepositStatus::Cancelled,
            'expired' => DepositStatus::Expired,
            default => DepositStatus::AwaitingPayment,
        };

        DB::transaction(function () use ($deposit, $payment, $status): void {
            $locked = WalletDeposit::query()->lockForUpdate()->findOrFail($deposit->id);
            if ($locked->status === DepositStatus::Credited) {
                return;
            }
            $locked->forceFill(['provider_status' => $payment->status, 'status' => $status])->save();
            if ($status->isTerminal()) {
                DB::afterCommit(static fn () => event(new DepositFailed($locked->id, $payment->status)));
            }
        });

        return $deposit->fresh() ?? $deposit;
    }

    private function assertPaymentMatches(WalletDeposit $deposit, GatewayPayment $payment): void
    {
        if ($payment->environment !== 'sandbox') {
            throw new WalletException('Le paiement GeniusPay n’appartient pas à la sandbox autorisée.');
        }

        if ($payment->amountMinor !== $deposit->amount_minor || strtoupper($payment->currency) !== strtoupper($deposit->currency)) {
            throw new WalletException('Le montant ou la devise GeniusPay ne correspond pas au dépôt Wasplex.');
        }

        if ($deposit->provider_reference !== null && ! hash_equals($deposit->provider_reference, $payment->reference)) {
            throw new WalletException('La référence GeniusPay du dépôt est incohérente.');
        }
    }
}
