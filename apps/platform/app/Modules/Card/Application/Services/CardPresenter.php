<?php

declare(strict_types=1);

namespace App\Modules\Card\Application\Services;

use App\Modules\Card\Infrastructure\Models\Card;

final class CardPresenter
{
    public static function card(Card $card): array
    {
        $card->loadMissing('offerVersion.offer', 'account.profile');

        return [
            'id' => $card->id,
            'public_identifier' => $card->public_identifier,
            'status' => $card->status,
            'display_name' => $card->account->profile?->display_name ?: 'Membre Wasplex',
            'offer' => [
                'code' => $card->offerVersion->offer->code,
                'name' => $card->offerVersion->offer->name,
            ],
            'issued_at' => $card->issued_at?->toIso8601String(),
            'activated_at' => $card->activated_at?->toIso8601String(),
            'expires_at' => $card->expires_at?->toIso8601String(),
            'supports_virtual' => (bool) $card->offerVersion->supports_virtual,
            'supports_physical' => (bool) $card->offerVersion->supports_physical,
        ];
    }
}
