<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Application\Contracts;

use App\Modules\Ledger\Domain\ValueObjects\LedgerAccountReference;
use App\Modules\Wallet\Infrastructure\Models\UserWallet;

/**
 * The only way another module (Feed, P009) may credit a user's Wallet —
 * always via a Ledger transaction posted by the caller, never a direct
 * balance write (docs/CLAUDE.md §7). This contract only ever constructs
 * the destination reference and reads the projection; it never posts a
 * transaction itself, so the caller's own transaction stays atomic and
 * balanced end to end (mirrors AdvertiserWalletReservationContract's
 * `capture()` destination pattern, docs/chantiers/P009-CHANTIER.md §4).
 */
interface UserWalletContract
{
    public function getOrCreate(string $accountId): UserWallet;

    public function availableAccountReference(string $accountId): LedgerAccountReference;

    public function balanceMinor(string $accountId): int;
}
