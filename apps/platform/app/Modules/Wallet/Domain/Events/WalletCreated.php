<?php

namespace App\Modules\Wallet\Domain\Events;

final readonly class WalletCreated
{
    public function __construct(public string $walletId, public string $spaceId, public string $kind) {}
}
