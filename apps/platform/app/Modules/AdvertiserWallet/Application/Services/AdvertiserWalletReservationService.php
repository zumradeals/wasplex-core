<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Application\Services;

use App\Modules\AdvertiserWallet\Application\Contracts\AdvertiserWalletReservationContract;
use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Domain\ValueObjects\LedgerAccountReference;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Shared\Money\Money;

/**
 * Moves value from "available" to "reserved" within the same organization's
 * liability accounts (docs/13 §24) — an internal transfer, not a real cash
 * movement, so no clearing account is involved (both entries stay under
 * LIABILITY_ADVERTISER for the same owner, balance always zero net effect
 * on total liability, only reclassified between the two accounts).
 */
final class AdvertiserWalletReservationService implements AdvertiserWalletReservationContract
{
    public const RESERVED_ACCOUNT_CODE = 'advertiser.budget.reserved';

    public function __construct(
        private readonly LedgerPostingContract $posting,
        private readonly AdvertiserWalletQueryService $queries,
    ) {}

    public function reserve(string $organizationId, string $campaignId, int $amountMinor, string $idempotencyKey): string
    {
        $available = $this->queries->availableBalanceMinor($organizationId);

        if ($available < $amountMinor) {
            throw new InsufficientAdvertiserBalanceException($available, $amountMinor);
        }

        $availableAccount = LedgerAccountReference::forOrganization(
            AdvertiserWalletQueryService::AVAILABLE_ACCOUNT_CODE,
            $organizationId,
            'LIABILITY_ADVERTISER',
            'WP',
        );
        $reservedAccount = LedgerAccountReference::forOrganization(
            self::RESERVED_ACCOUNT_CODE,
            $organizationId,
            'LIABILITY_ADVERTISER',
            'WP',
        );

        $transaction = $this->posting->post(new PostLedgerTransaction(
            type: 'CAMPAIGN_BUDGET_RESERVED',
            sourceModule: 'campaigns',
            idempotencyKey: $idempotencyKey,
            entries: [
                LedgerEntryInput::debit($availableAccount, Money::of($amountMinor, 'WP'), 'Réservation budget campagne'),
                LedgerEntryInput::credit($reservedAccount, Money::of($amountMinor, 'WP'), 'Réservation budget campagne'),
            ],
            businessReference: $campaignId,
        ));

        return $transaction->id;
    }
}
