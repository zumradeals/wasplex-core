<?php

namespace App\Modules\Wallet\Application\Data;

final readonly class CreateGatewayPayment
{
    /** @param array<string, string> $metadata */
    public function __construct(
        public string $depositId,
        public int $amountMinor,
        public string $currency,
        public string $description,
        public string $successUrl,
        public string $errorUrl,
        public array $metadata,
    ) {}
}
