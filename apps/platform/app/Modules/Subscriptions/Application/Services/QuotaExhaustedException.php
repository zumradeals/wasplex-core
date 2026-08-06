<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Services;

use App\Shared\Http\AppException;

/**
 * docs/03 §6: "quand le quota est atteint, aucune nouvelle publicité
 * commerciale n'est injectée". Thrown by consume() so a future
 * caller (P008/P009) never over-consumes the counter.
 */
final class QuotaExhaustedException extends AppException
{
    public function __construct(string $accountId)
    {
        parent::__construct('QUOTA_EXHAUSTED', "Quota publicitaire mensuel épuisé pour le compte {$accountId}.", status: 409);
    }
}
