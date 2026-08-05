<?php

namespace App\Modules\ValueEngine\Application\Services;

use App\Modules\ValueEngine\Domain\Exceptions\ValueEngineException;
use App\Modules\ValueEngine\Infrastructure\Models\AttentionSession;

final class AttentionTokenSigner
{
    public function sign(string $idempotencyKey, string $accountId, string $deviceSessionId): string
    {
        $secret = (string) config('app.key');
        if ($secret === '') {
            throw new ValueEngineException('La clé applicative est requise pour signer les sessions d’attention.');
        }

        return hash_hmac('sha256', $idempotencyKey.'|'.$accountId.'|'.$deviceSessionId, $secret);
    }

    public function assertValid(AttentionSession $session, string $attentionToken): void
    {
        if (! hash_equals($session->token_hash, hash('sha256', $attentionToken))) {
            throw new ValueEngineException('Le jeton de session d’attention est invalide.');
        }
    }
}
