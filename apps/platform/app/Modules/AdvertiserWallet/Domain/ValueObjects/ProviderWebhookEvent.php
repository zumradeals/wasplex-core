<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Domain\ValueObjects;

final class ProviderWebhookEvent
{
    public function __construct(
        public readonly string $eventType,
        public readonly ?string $providerReference,
        public readonly ?int $amountMinor,
        public readonly ?string $currency,
        public readonly ?string $providerStatusRaw,
        public readonly string $normalizedStatus,
        public readonly string $environment,
    ) {}
}
