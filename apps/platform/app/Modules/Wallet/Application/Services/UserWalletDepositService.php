<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Application\Services;

use App\Modules\Wallet\Infrastructure\Models\UserWalletDeposit;
use App\Shared\Http\AppException;
use App\Shared\Payments\PaymentProviderContract;
use App\Shared\Payments\ValueObjects\CreatePaymentRequest;
use Illuminate\Support\Str;

final class UserWalletDepositService
{
    public function __construct(
        private readonly PaymentProviderContract $provider,
        private readonly UserWalletCreditor $creditor,
    ) {}

    public function create(string $accountId, int $amountMinor): UserWalletDeposit
    {
        $deposit = UserWalletDeposit::query()->create([
            'account_id' => $accountId,
            'amount_minor' => $amountMinor,
            'currency' => 'XOF',
            'status' => UserWalletDeposit::STATUS_CREATED,
            'provider_code' => 'geniuspay',
            'idempotency_key' => (string) Str::ulid(),
        ]);

        $appUrl = rtrim((string) config('app.url'), '/');
        $result = $this->provider->createPayment(new CreatePaymentRequest(
            amountMinor: $deposit->amount_minor,
            currency: $deposit->currency,
            internalReference: $deposit->id,
            idempotencyKey: $deposit->idempotency_key,
            successUrl: $appUrl.'/app?tab=wallet&payment=wallet-deposit-success&deposit_id='.$deposit->id,
            errorUrl: $appUrl.'/app?tab=wallet&payment=wallet-deposit-failed&deposit_id='.$deposit->id,
            description: "Dépôt Wallet Wasplex {$deposit->id}",
            metadata: [
                'payment_context' => 'user_wallet_deposit',
                'deposit_id' => $deposit->id,
                'account_id' => $accountId,
            ],
        ));

        $deposit->update([
            'status' => $this->mapStatus($result->normalizedStatus),
            'provider_reference' => $result->providerReference,
            'checkout_url' => $result->checkoutUrl,
            'metadata' => ['environment' => $result->environment],
        ]);

        return $deposit->refresh();
    }

    public function reconcile(string $depositId, string $accountId): UserWalletDeposit
    {
        $deposit = UserWalletDeposit::query()
            ->whereKey($depositId)
            ->where('account_id', $accountId)
            ->first();

        if ($deposit === null) {
            throw new AppException('WALLET_DEPOSIT_NOT_FOUND', 'Dépôt Wallet introuvable.', [], 404);
        }

        if ($deposit->isTerminal()) {
            return $deposit;
        }

        if ($deposit->provider_reference === null) {
            throw new AppException(
                'WALLET_DEPOSIT_NOT_STARTED',
                'Le dépôt n’a pas encore de référence GeniusPay.',
                ['deposit_id' => $deposit->id],
                409,
            );
        }

        $verified = $this->provider->fetchPaymentStatus($deposit->provider_reference);

        if ($verified->amountMinor !== null && $verified->amountMinor !== $deposit->amount_minor) {
            throw new AppException(
                'WALLET_DEPOSIT_AMOUNT_MISMATCH',
                'Le montant confirmé par GeniusPay ne correspond pas au dépôt attendu.',
                ['deposit_id' => $deposit->id],
                409,
            );
        }

        if ($verified->currency !== null && $verified->currency !== $deposit->currency) {
            throw new AppException(
                'WALLET_DEPOSIT_CURRENCY_MISMATCH',
                'La devise confirmée par GeniusPay ne correspond pas au dépôt attendu.',
                ['deposit_id' => $deposit->id],
                409,
            );
        }

        if ($verified->normalizedStatus === 'confirmed') {
            $deposit->update(['status' => UserWalletDeposit::STATUS_CONFIRMED]);

            return $this->creditor->credit($deposit->refresh());
        }

        if ($verified->normalizedStatus === 'rejected') {
            $deposit->update(['status' => UserWalletDeposit::STATUS_REJECTED]);
        } elseif ($verified->normalizedStatus === 'expired') {
            $deposit->update(['status' => UserWalletDeposit::STATUS_EXPIRED]);
        } elseif ($verified->normalizedStatus === 'awaiting_payment') {
            $deposit->update(['status' => UserWalletDeposit::STATUS_AWAITING_PAYMENT]);
        }

        return $deposit->refresh();
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'awaiting_payment' => UserWalletDeposit::STATUS_AWAITING_PAYMENT,
            'confirmed' => UserWalletDeposit::STATUS_CONFIRMED,
            'rejected' => UserWalletDeposit::STATUS_REJECTED,
            'expired' => UserWalletDeposit::STATUS_EXPIRED,
            default => UserWalletDeposit::STATUS_CREATED,
        };
    }
}
