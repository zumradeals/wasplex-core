<?php

declare(strict_types=1);

namespace App\Modules\Card\Application\Services;

use App\Modules\Card\Infrastructure\Models\Card;
use App\Modules\Card\Infrastructure\Models\CardAuditEvent;

final class CardAudit
{
    public function record(Card $card, string $eventType, array $metadata = []): void
    {
        CardAuditEvent::query()->create([
            'card_id' => $card->id,
            'account_id' => $card->account_id,
            'event_type' => $eventType,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
