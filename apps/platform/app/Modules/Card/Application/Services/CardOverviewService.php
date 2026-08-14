<?php

declare(strict_types=1);

namespace App\Modules\Card\Application\Services;

use App\Modules\Card\Infrastructure\Models\Card;
use App\Modules\Card\Infrastructure\Models\CardOfferVersion;
use App\Modules\Identity\Infrastructure\Models\Account;

final class CardOverviewService
{
    public function get(Account $account): array
    {
        $card = Card::query()
            ->with('offerVersion.offer', 'account.profile')
            ->where('account_id', $account->id)
            ->where('status', '!=', Card::STATUS_CLOSED)
            ->latest('issued_at')
            ->first();

        $baseVersion = CardOfferVersion::query()
            ->with('offer')
            ->where('status', 'published')
            ->whereHas('offer', fn ($query) => $query->where('code', 'WASPLEX_BASE')->where('status', 'active'))
            ->orderByDesc('version')
            ->firstOrFail();

        return [
            'card' => $card === null ? null : CardPresenter::card($card),
            'offer' => [
                'code' => $baseVersion->offer->code,
                'name' => $baseVersion->offer->name,
                'description' => $baseVersion->offer->description,
                'price_minor' => (int) $baseVersion->price_minor,
                'currency' => $baseVersion->currency,
                'supports_virtual' => (bool) $baseVersion->supports_virtual,
                'supports_physical' => (bool) $baseVersion->supports_physical,
            ],
        ];
    }
}
