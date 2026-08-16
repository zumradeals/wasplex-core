<?php

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use App\Modules\Live\Infrastructure\Models\LiveViewerSession;
use Illuminate\Support\Facades\Broadcast;

// docs/07 §61 (temps réel), docs/chantiers/P011-CHANTIER.md : un compte ne
// peut écouter que son propre canal Wallet — jamais celui d'un autre.
Broadcast::channel('wallet.{accountId}', fn (Account $account, string $accountId): bool => $account->id === $accountId);

// P018-D : propositions de place rémunérée strictement privées au compte.
// Le canal ne transporte aucun budget annonceur ni critère de ciblage.
Broadcast::channel(
    'live-reward.{accountId}',
    fn (Account $account, string $accountId): bool => $account->id === $accountId,
);

// P018-B : le social Live reste privé à la salle. L'hôte propriétaire peut
// écouter son Live ; un membre doit disposer d'une session spectateur Wasplex
// active. LiveKit ne transporte jamais les commentaires ni la modération.
Broadcast::channel('live.{liveId}', function (Account $account, string $liveId): bool {
    $live = LiveEvent::query()->find($liveId);
    if ($live === null) {
        return false;
    }

    if ($live->owner_account_id === $account->id) {
        return true;
    }

    return LiveViewerSession::query()
        ->where('live_id', $liveId)
        ->where('account_id', $account->id)
        ->whereIn('status', [LiveViewerSession::STATUS_WATCHING, LiveViewerSession::STATUS_PAUSED])
        ->exists();
});
