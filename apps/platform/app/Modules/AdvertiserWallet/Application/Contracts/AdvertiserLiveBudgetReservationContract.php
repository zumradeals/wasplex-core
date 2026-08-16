<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Application\Contracts;

use App\Modules\AdvertiserWallet\Application\Services\InsufficientAdvertiserBalanceException;

/**
 * Financial boundary used by Live to reserve and release an advertiser's
 * sponsored-Live budget without reading or mutating Wallet/Ledger tables.
 */
interface AdvertiserLiveBudgetReservationContract
{
    /**
     * @throws InsufficientAdvertiserBalanceException
     */
    public function reserve(string $organizationId, string $liveId, int $amountMinor, string $idempotencyKey): string;

    public function release(string $organizationId, string $liveId, int $amountMinor, string $idempotencyKey): string;
}
