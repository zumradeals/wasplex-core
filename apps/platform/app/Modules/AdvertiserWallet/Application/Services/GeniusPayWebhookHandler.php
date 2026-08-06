<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Application\Services;

use App\Modules\AdvertiserWallet\Infrastructure\Models\AdvertiserWalletDeposit;
use App\Modules\AdvertiserWallet\Infrastructure\Models\AdvertiserWalletDepositEvent;
use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Domain\ValueObjects\LedgerAccountReference;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Shared\Money\Money;
use App\Shared\Payments\PaymentProviderContract;

/**
 * docs/chantiers/HOTFIX-P003-GENIUSPAY-SANDBOX.md §4: "crédit uniquement
 * après webhook signé et revérification serveur" — the webhook payload
 * itself is never trusted for the credit decision, only as a trigger to
 * re-check the provider directly (fetchDepositStatus).
 */
final class GeniusPayWebhookHandler
{
    public function __construct(
        private readonly PaymentProviderContract $provider,
        private readonly LedgerPostingContract $posting,
    ) {}

    public function handle(string $rawPayload, array $headers): void
    {
        if (! $this->provider->verifyWebhookSignature($rawPayload, $headers)) {
            AdvertiserWalletDepositEvent::query()->create([
                'provider_code' => 'geniuspay',
                'signature_valid' => false,
                'status' => AdvertiserWalletDepositEvent::STATUS_REJECTED,
            ]);

            throw new InvalidWebhookSignatureException;
        }

        $event = $this->provider->parseWebhookPayload($rawPayload);

        $deposit = $event->providerReference !== null
            ? AdvertiserWalletDeposit::query()->where('provider_reference', $event->providerReference)->first()
            : null;

        if ($deposit === null) {
            AdvertiserWalletDepositEvent::query()->create([
                'provider_code' => 'geniuspay',
                'event_type' => $event->eventType,
                'provider_status_raw' => $event->providerStatusRaw,
                'signature_valid' => true,
                'status' => AdvertiserWalletDepositEvent::STATUS_REJECTED,
            ]);

            return;
        }

        if ($deposit->isTerminal()) {
            AdvertiserWalletDepositEvent::query()->create([
                'deposit_id' => $deposit->id,
                'provider_code' => 'geniuspay',
                'event_type' => $event->eventType,
                'provider_status_raw' => $event->providerStatusRaw,
                'signature_valid' => true,
                'status' => AdvertiserWalletDepositEvent::STATUS_DUPLICATED,
            ]);

            return;
        }

        // Server-side re-verification: never trust the webhook body alone.
        $verified = $this->provider->fetchPaymentStatus($deposit->provider_reference);

        AdvertiserWalletDepositEvent::query()->create([
            'deposit_id' => $deposit->id,
            'provider_code' => 'geniuspay',
            'event_type' => $event->eventType,
            'provider_status_raw' => $verified->providerStatusRaw,
            'signature_valid' => true,
            'status' => AdvertiserWalletDepositEvent::STATUS_VERIFIED,
        ]);

        match ($verified->normalizedStatus) {
            'confirmed' => $this->creditDeposit($deposit),
            'rejected' => $deposit->update(['status' => AdvertiserWalletDeposit::STATUS_REJECTED]),
            'expired' => $deposit->update(['status' => AdvertiserWalletDeposit::STATUS_EXPIRED]),
            default => null,
        };
    }

    private function creditDeposit(AdvertiserWalletDeposit $deposit): void
    {
        $organizationAccount = LedgerAccountReference::forOrganization(
            AdvertiserWalletQueryService::AVAILABLE_ACCOUNT_CODE,
            $deposit->organization_id,
            'LIABILITY_ADVERTISER',
            'WP',
        );
        $clearingAccount = LedgerAccountReference::system('wasplex.cash.clearing', 'ASSET', 'WP');

        $transaction = $this->posting->post(new PostLedgerTransaction(
            type: 'ADVERTISER_DEPOSIT_CREDIT',
            sourceModule: 'advertiser_wallet',
            idempotencyKey: "advertiser-deposit:{$deposit->id}",
            entries: [
                LedgerEntryInput::debit($clearingAccount, Money::of($deposit->amount_minor, 'WP'), "Dépôt GeniusPay {$deposit->provider_reference}"),
                LedgerEntryInput::credit($organizationAccount, Money::of($deposit->amount_minor, 'WP'), "Dépôt GeniusPay {$deposit->provider_reference}"),
            ],
            businessReference: $deposit->provider_reference,
            createdBy: $deposit->initiated_by,
        ));

        $deposit->update([
            'status' => AdvertiserWalletDeposit::STATUS_CREDITED,
            'ledger_transaction_id' => $transaction->id,
        ]);
    }
}
