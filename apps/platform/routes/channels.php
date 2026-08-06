<?php

use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Support\Facades\Broadcast;

// docs/07 §61 (temps réel), docs/chantiers/P011-CHANTIER.md : un compte ne
// peut écouter que son propre canal Wallet — jamais celui d'un autre.
Broadcast::channel('wallet.{accountId}', fn (Account $account, string $accountId): bool => $account->id === $accountId);
