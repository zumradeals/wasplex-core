<?php

declare(strict_types=1);

namespace App\Modules\Card\Application\Services;

use App\Modules\Card\Infrastructure\Models\CardOperation;
use App\Modules\Card\Infrastructure\Models\CardQrToken;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\PersonalProfile;
use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Modules\Wallet\Application\Services\UserWalletQueryService;
use App\Modules\Wallet\Infrastructure\Models\UserWallet;
use App\Shared\Http\AppException;
use App\Shared\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CardPaymentService
{
    private const PILOT_MAX_PAYMENT_MINOR = 1_000_000;

    public function __construct(
        private readonly LedgerPostingContract $posting,
        private readonly UserWalletQueryService $wallet,
        private readonly CardQrService $qr,
        private readonly CardAudit $audit,
    ) {}

    public function preview(string $payerAccountId, string $payload): array
    {
        $preview = $this->qr->previewReceive($payload, $payerAccountId);
        $this->wallet->getOrCreate($payerAccountId);

        return $preview + [
            'available_minor' => $this->wallet->balanceMinor($payerAccountId),
            'max_payment_minor' => self::PILOT_MAX_PAYMENT_MINOR,
        ];
    }

    public function pay(
        string $payerAccountId,
        string $payload,
        ?int $requestedAmountMinor,
        string $idempotencyKey,
    ): CardOperation {
        $tokenHash = $this->qr->tokenHash($payload);
        $notifyBalances = false;

        $result = DB::transaction(function () use (
            $payerAccountId,
            $payload,
            $requestedAmountMinor,
            $idempotencyKey,
            $tokenHash,
            &$notifyBalances,
        ): CardOperation {
            $existing = CardOperation::query()
                ->where('payer_account_id', $payerAccountId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $token = CardQrToken::query()->where('token_hash', $tokenHash)->first();
                if ($token === null || $existing->qr_token_id !== $token->id) {
                    throw new AppException(
                        'CARD_PAYMENT_IDEMPOTENCY_CONFLICT',
                        'Cette confirmation a déjà été utilisée pour un autre paiement.',
                        [],
                        409,
                    );
                }
                if ($requestedAmountMinor !== null && $existing->amount_minor !== $requestedAmountMinor) {
                    throw new AppException(
                        'CARD_PAYMENT_IDEMPOTENCY_CONFLICT',
                        'Cette confirmation a déjà été utilisée avec un autre montant.',
                        [],
                        409,
                    );
                }
                if ($existing->status === CardOperation::STATUS_POSTED) {
                    return $existing;
                }
            }

            $token = $this->qr->lockReceiveForPayment($payload);
            $beneficiaryAccountId = (string) $token->card->account_id;
            if ($beneficiaryAccountId === $payerAccountId) {
                throw new AppException('CARD_PAYMENT_SELF', 'Vous ne pouvez pas payer votre propre Carte Wasplex.', [], 409);
            }

            $amountMinor = $this->resolveAmount($token, $requestedAmountMinor);
            if ($amountMinor > self::PILOT_MAX_PAYMENT_MINOR) {
                throw new AppException(
                    'CARD_PAYMENT_LIMIT_EXCEEDED',
                    'Ce paiement dépasse le plafond actuel de la Carte Wasplex.',
                    ['max_payment_minor' => self::PILOT_MAX_PAYMENT_MINOR],
                    409,
                );
            }

            $beneficiary = Account::query()->find($beneficiaryAccountId);
            if ($beneficiary === null || ! $beneficiary->isActive()) {
                throw new AppException('CARD_PAYMENT_RECIPIENT_UNAVAILABLE', 'Ce bénéficiaire ne peut pas recevoir de paiement.', [], 409);
            }

            $this->wallet->getOrCreate($payerAccountId);
            $this->wallet->getOrCreate($beneficiaryAccountId);

            UserWallet::query()->where('account_id', $payerAccountId)->lockForUpdate()->firstOrFail();
            $availableMinor = $this->wallet->balanceMinor($payerAccountId);
            if ($availableMinor < $amountMinor) {
                throw new AppException(
                    'CARD_PAYMENT_INSUFFICIENT_BALANCE',
                    'Votre solde Wallet est insuffisant pour ce paiement.',
                    ['available_minor' => $availableMinor],
                    409,
                );
            }

            $operation = $existing ?? CardOperation::query()->create([
                'payer_account_id' => $payerAccountId,
                'beneficiary_account_id' => $beneficiaryAccountId,
                'beneficiary_card_id' => $token->card_id,
                'qr_token_id' => $token->id,
                'amount_minor' => $amountMinor,
                'currency' => 'WP',
                'note' => $token->note,
                'status' => CardOperation::STATUS_PENDING,
                'idempotency_key' => $idempotencyKey,
                'receipt_reference' => 'WPC-'.strtoupper(substr((string) Str::ulid(), -12)),
            ]);

            $payerName = PersonalProfile::query()->where('account_id', $payerAccountId)->value('display_name');
            $beneficiaryName = PersonalProfile::query()->where('account_id', $beneficiaryAccountId)->value('display_name');
            $sentDescription = 'Paiement Carte envoyé'.($beneficiaryName ? ' à '.$beneficiaryName : '');
            $receivedDescription = 'Paiement Carte reçu'.($payerName ? ' de '.$payerName : '');

            $transaction = $this->posting->post(new PostLedgerTransaction(
                type: 'CARD_PAYMENT',
                sourceModule: 'card',
                idempotencyKey: "card-payment:{$operation->id}",
                entries: [
                    LedgerEntryInput::debit(
                        $this->wallet->availableAccountReference($payerAccountId),
                        Money::of($amountMinor, 'WP'),
                        $sentDescription,
                    ),
                    LedgerEntryInput::credit(
                        $this->wallet->availableAccountReference($beneficiaryAccountId),
                        Money::of($amountMinor, 'WP'),
                        $receivedDescription,
                    ),
                ],
                businessReference: $operation->id,
                createdBy: $payerAccountId,
                metadata: [
                    'card_operation_id' => $operation->id,
                    'beneficiary_card_id' => $token->card_id,
                    'qr_token_id' => $token->id,
                    'payer_account_id' => $payerAccountId,
                    'beneficiary_account_id' => $beneficiaryAccountId,
                    'receipt_reference' => $operation->receipt_reference,
                ],
            ));

            $operation->update([
                'status' => CardOperation::STATUS_POSTED,
                'ledger_transaction_id' => $transaction->id,
                'posted_at' => now(),
            ]);
            $this->qr->consumeReceive($token);
            $this->audit->record($token->card, 'CardPaymentReceived', [
                'operation_id' => $operation->id,
                'amount_minor' => $amountMinor,
                'receipt_reference' => $operation->receipt_reference,
            ]);
            $notifyBalances = true;

            return $operation->refresh();
        });

        if ($notifyBalances && $result->ledger_transaction_id !== null) {
            $this->wallet->notifyBalanceChanged(
                $result->payer_account_id,
                $result->amount_minor,
                'card',
                'debit',
                $result->ledger_transaction_id,
            );
            $this->wallet->notifyBalanceChanged(
                $result->beneficiary_account_id,
                $result->amount_minor,
                'card',
                'credit',
                $result->ledger_transaction_id,
            );
        }

        return $result;
    }

    public function receipt(CardOperation $operation, string $accountId): array
    {
        $operation->loadMissing('payer.profile', 'beneficiary.profile', 'beneficiaryCard.offerVersion.offer');
        $sent = $operation->payer_account_id === $accountId;
        $counterparty = $sent ? $operation->beneficiary : $operation->payer;

        return [
            'id' => $operation->id,
            'receipt_reference' => $operation->receipt_reference,
            'status' => $operation->status,
            'direction' => $sent ? 'sent' : 'received',
            'amount_minor' => $operation->amount_minor,
            'currency' => $operation->currency,
            'note' => $operation->note,
            'counterparty' => [
                'display_name' => $counterparty->profile?->display_name ?: 'Membre Wasplex',
                'public_identifier' => $sent ? $operation->beneficiaryCard->public_identifier : null,
            ],
            'posted_at' => $operation->posted_at?->toIso8601String(),
        ];
    }

    public function history(string $accountId, int $limit = 20): array
    {
        return CardOperation::query()
            ->with('payer.profile', 'beneficiary.profile', 'beneficiaryCard.offerVersion.offer')
            ->where(function ($query) use ($accountId): void {
                $query->where('payer_account_id', $accountId)
                    ->orWhere('beneficiary_account_id', $accountId);
            })
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (CardOperation $operation): array => $this->receipt($operation, $accountId))
            ->values()
            ->all();
    }

    private function resolveAmount(CardQrToken $token, ?int $requestedAmountMinor): int
    {
        if ($token->amount_minor !== null) {
            if ($requestedAmountMinor !== null && $requestedAmountMinor !== $token->amount_minor) {
                throw new AppException('CARD_PAYMENT_AMOUNT_LOCKED', 'Le montant de cette demande de paiement ne peut pas être modifié.', [], 409);
            }

            return $token->amount_minor;
        }

        if ($requestedAmountMinor === null || $requestedAmountMinor < 1) {
            throw new AppException('CARD_PAYMENT_AMOUNT_REQUIRED', 'Saisissez un montant supérieur à zéro.');
        }

        return $requestedAmountMinor;
    }
}
