<?php

namespace App\Modules\Wallet\Application\Data;

use DateTimeImmutable;

final readonly class GatewayPayment
{
    /** @param array<string, scalar|null> $metadata */
    public function __construct(
        public string $id,
        public string $reference,
        public int $amountMinor,
        public int $feeMinor,
        public string $currency,
        public string $status,
        public ?string $checkoutUrl,
        public string $environment,
        public array $metadata = [],
        public ?DateTimeImmutable $expiresAt = null,
    ) {}

    public function succeeded(): bool
    {
        return in_array($this->status, ['completed', 'success', 'succeeded'], true);
    }
}
