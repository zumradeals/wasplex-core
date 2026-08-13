<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Application\Services;

use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Domain\ValueObjects\LedgerAccountReference;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Modules\Wallet\Infrastructure\Models\UserWalletDeposit;
use App\Shared\Money\Money;

final class UserWalletCreditor
{
    public function __construct(
        private readonly LedgerPostingContract $posting,
        private readonly UserWalletQueryService $wallet,
    ) {}

    public function credit(UserWalletDeposit $deposit): UserWalletDeposit
    {
        $description = 'Dépôt Wallet via GeniusPay';
        $transaction = $this->posting->post(new PostLedgerTransaction(
            type: 'USER_WALLET_DEPOSIT',
            sourceModule: 'wallet',
            idempotencyKey: "user-wallet-deposit:{$deposit->id}",
            entries: [
                LedgerEntryInput::debit(
                    LedgerAccountReference::system('wasplex.cash.clearing', 'ASSET', 'WP'),
                    Money::of($deposit->amount_minor, 'WP'),
                    $description,
                ),
                LedgerEntryInput::credit(
                    $this->wallet->availableAccountReference($deposit->account_id),
                    Money::of($deposit->amount_minor, 'WP'),
                    $description,
                ),
            ],
            businessReference: $deposit->provider_reference,
            createdBy: $deposit->account_id,
            metadata: ['deposit_id' => $deposit->id, 'provider' => 'geniuspay'],
        ));

        $deposit->update([
            'status' => UserWalletDeposit::STATUS_CREDITED,
            'ledger_transaction_id' => $transaction->id,
        ]);

        $this->wallet->notifyBalanceChanged(
            $deposit->account_id,
            $deposit->amount_minor,
            'deposit',
            'credit',
            $transaction->id,
        );

        return $deposit->refresh();
    }
}
