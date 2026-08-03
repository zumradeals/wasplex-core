<?php

namespace App\Modules\Wallet\Application\Data;

use DateTimeImmutable;

final readonly class GatewayWebhook
{
    /** @param array<string, scalar|null> $safePayload */
    public function __construct(
        public string $eventKey,
        public string $payloadHash,
        public string $eventName,
        public string $paymentReference,
        public string $paymentStatus,
        public int $amountMinor,
        public string $environment,
        public DateTimeImmutable $occurredAt,
        public array $safePayload,
    ) {}
}
