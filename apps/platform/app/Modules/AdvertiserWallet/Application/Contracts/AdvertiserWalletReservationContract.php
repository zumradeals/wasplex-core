<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Application\Contracts;

use App\Modules\AdvertiserWallet\Application\Services\InsufficientAdvertiserBalanceException;

/**
 * The only way another module (Campaigns, P006) may move value out of an
 * advertiser's available Wallet balance into a reserved state
 * (docs/13 §24: "budget réservé" is a Wallet-owned projection). Posts to
 * the Grand Livre via LedgerPostingContract — no direct balance mutation
 * (docs/CLAUDE.md §7).
 */
interface AdvertiserWalletReservationContract
{
    /**
     * @throws InsufficientAdvertiserBalanceException
     */
    public function reserve(string $organizationId, string $campaignId, int $amountMinor, string $idempotencyKey): string;

    /**
     * Reverses a prior reserve() — moves value back from "reserved" to
     * "available" (docs/chantiers/P007-CHANTIER.md §6: a rejected campaign
     * releases its reservation; an approved/suspended one keeps it).
     */
    public function release(string $organizationId, string $campaignId, int $amountMinor, string $idempotencyKey): string;
}
