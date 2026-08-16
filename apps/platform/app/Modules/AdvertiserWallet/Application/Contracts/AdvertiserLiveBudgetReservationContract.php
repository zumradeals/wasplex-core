<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Application\Contracts;

use App\Modules\AdvertiserWallet\Application\Services\InsufficientAdvertiserBalanceException;
use App\Modules\Ledger\Domain\ValueObjects\LedgerAccountReference;

/**
 * Financial boundary used by Live to reserve, capture and release a sponsored
 * Live budget without reading or mutating Wallet/Ledger tables directly.
 */
interface AdvertiserLiveBudgetReservationContract
{
    /**
     * @throws InsufficientAdvertiserBalanceException
     */
    public function reserve(string $organizationId, string $liveId, int $amountMinor, string $idempotencyKey): string;

    public function release(string $organizationId, string $liveId, int $amountMinor, string $idempotencyKey): string;

    /**
     * Captures one fully qualified rewarded Live block. The immutable split is
     * 50 % to the member and 50 % to Wasplex, so gross consumption is exactly
     * twice the member reward.
     */
    public function captureReward(
        string $organizationId,
        string $liveId,
        int $userRewardMinor,
        LedgerAccountReference $destination,
        string $idempotencyKey,
    ): string;
}
