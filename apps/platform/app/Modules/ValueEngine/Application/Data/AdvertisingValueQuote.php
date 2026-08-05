<?php

namespace App\Modules\ValueEngine\Application\Data;

final readonly class AdvertisingValueQuote
{
    public function __construct(
        public int $grossAmountMinor,
        public int $userAmountMinor,
        public int $wasplexAmountMinor,
        public string $unit,
        public string $currency,
        public string $ruleVersionId,
        public string $calculationHash,
    ) {}
}
