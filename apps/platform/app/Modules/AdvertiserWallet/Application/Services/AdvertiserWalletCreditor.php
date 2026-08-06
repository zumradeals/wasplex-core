<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Application\Services;

use App\Modules\AdvertiserWallet\Infrastructure\Models\AdvertiserWalletDeposit;
use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Domain\ValueObjects\LedgerAccountReference;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Shared\Money\Money;

/**
 * The single place a confirmed deposit — GeniusPay or supervised (docs/13
 * §28) — is posted to the Grand Livre and marked credited. Both
 * GeniusPayWebhookHandler and SupervisedDepositService call this so the
 * ledger entries and idempotency key are identical regardless of how the
 * deposit was confirmed.
 */
final class AdvertiserWalletCreditor
{
    public function __construct(private readonly LedgerPostingContract $posting) {}

    public function credit(AdvertiserWalletDeposit $deposit, string $description): AdvertiserWalletDeposit
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
                LedgerEntryInput::debit($clearingAccount, Money::of($deposit->amount_minor, 'WP'), $description),
                LedgerEntryInput::credit($organizationAccount, Money::of($deposit->amount_minor, 'WP'), $description),
            ],
            businessReference: $deposit->provider_reference,
            createdBy: $deposit->initiated_by,
        ));

        $deposit->update([
            'status' => AdvertiserWalletDeposit::STATUS_CREDITED,
            'ledger_transaction_id' => $transaction->id,
        ]);

        return $deposit->refresh();
    }
}
