<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Application\Contracts;

use App\Modules\AdvertiserWallet\Application\Services\InsufficientAdvertiserBalanceException;
use App\Modules\Ledger\Domain\ValueObjects\LedgerAccountReference;

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

    /**
     * Spends a reserved amount permanently: debits "reserved" and credits
     * the caller-supplied destination — never the advertiser's own
     * "available" account (docs/chantiers/P009-CHANTIER.md §4). Used by
     * Feed to pay out a validated ad delivery straight into the viewer's
     * Wallet, in one balanced transaction the caller never has to
     * assemble itself.
     */
    public function capture(
        string $organizationId,
        string $campaignId,
        int $amountMinor,
        LedgerAccountReference $destination,
        string $idempotencyKey,
    ): string;
}
