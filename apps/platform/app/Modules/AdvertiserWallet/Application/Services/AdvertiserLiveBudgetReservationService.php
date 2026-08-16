<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Application\Services;

use App\Modules\AdvertiserWallet\Application\Contracts\AdvertiserLiveBudgetReservationContract;
use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Domain\ValueObjects\LedgerAccountReference;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Shared\Money\Money;

final class AdvertiserLiveBudgetReservationService implements AdvertiserLiveBudgetReservationContract
{
    public const RESERVED_ACCOUNT_CODE = 'advertiser.live.budget.reserved';

    public function __construct(
        private readonly LedgerPostingContract $posting,
        private readonly AdvertiserWalletQueryService $queries,
    ) {}

    public function reserve(string $organizationId, string $liveId, int $amountMinor, string $idempotencyKey): string
    {
        $available = $this->queries->availableBalanceMinor($organizationId);

        if ($available < $amountMinor) {
            throw new InsufficientAdvertiserBalanceException($available, $amountMinor);
        }

        $transaction = $this->posting->post(new PostLedgerTransaction(
            type: 'LIVE_BUDGET_RESERVED',
            sourceModule: 'live',
            idempotencyKey: $idempotencyKey,
            entries: [
                LedgerEntryInput::debit(
                    LedgerAccountReference::forOrganization(
                        AdvertiserWalletQueryService::AVAILABLE_ACCOUNT_CODE,
                        $organizationId,
                        'LIABILITY_ADVERTISER',
                        'WP',
                    ),
                    Money::of($amountMinor, 'WP'),
                    'Réservation budget Live sponsorisé',
                ),
                LedgerEntryInput::credit(
                    LedgerAccountReference::forOrganization(
                        self::RESERVED_ACCOUNT_CODE,
                        $organizationId,
                        'LIABILITY_ADVERTISER',
                        'WP',
                    ),
                    Money::of($amountMinor, 'WP'),
                    'Budget réservé pour Live sponsorisé',
                ),
            ],
            businessReference: $liveId,
        ));

        return $transaction->id;
    }

    public function release(string $organizationId, string $liveId, int $amountMinor, string $idempotencyKey): string
    {
        $transaction = $this->posting->post(new PostLedgerTransaction(
            type: 'LIVE_BUDGET_RELEASED',
            sourceModule: 'live',
            idempotencyKey: $idempotencyKey,
            entries: [
                LedgerEntryInput::debit(
                    LedgerAccountReference::forOrganization(
                        self::RESERVED_ACCOUNT_CODE,
                        $organizationId,
                        'LIABILITY_ADVERTISER',
                        'WP',
                    ),
                    Money::of($amountMinor, 'WP'),
                    'Libération budget Live sponsorisé',
                ),
                LedgerEntryInput::credit(
                    LedgerAccountReference::forOrganization(
                        AdvertiserWalletQueryService::AVAILABLE_ACCOUNT_CODE,
                        $organizationId,
                        'LIABILITY_ADVERTISER',
                        'WP',
                    ),
                    Money::of($amountMinor, 'WP'),
                    'Retour budget Live vers le Wallet disponible',
                ),
            ],
            businessReference: $liveId,
        ));

        return $transaction->id;
    }
}
