<?php

declare(strict_types=1);

namespace App\Shared\Payments\ValueObjects;

final class CreatePaymentRequest
{
    public function __construct(
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $internalReference,
        public readonly string $idempotencyKey,
    ) {}
}
