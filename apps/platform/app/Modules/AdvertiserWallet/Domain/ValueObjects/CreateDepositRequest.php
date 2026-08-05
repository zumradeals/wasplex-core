<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Domain\ValueObjects;

final class CreateDepositRequest
{
    public function __construct(
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $internalReference,
        public readonly string $idempotencyKey,
    ) {}
}
