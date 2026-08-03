<?php

namespace App\Modules\Wallet\Domain\Events;

final readonly class WalletBalanceChanged
{
    public function __construct(
        public string $walletId,
        public int $availableMinor,
        public int $reservedMinor,
        public int $pendingMinor,
    ) {}
}
