<?php

namespace App\Modules\ValueEngine\Application\Data;

use App\Modules\ValueEngine\Infrastructure\Models\AttentionSession;
use App\Modules\ValueEngine\Infrastructure\Models\ValueAttempt;

final readonly class StartedValueAttempt
{
    public function __construct(
        public ValueAttempt $attempt,
        public AttentionSession $attentionSession,
        public AdvertisingValueQuote $quote,
        public string $attentionToken,
    ) {}
}
