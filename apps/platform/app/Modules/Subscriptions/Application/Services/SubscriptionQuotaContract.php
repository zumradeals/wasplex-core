<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Services;

use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionQuotaCounter;

/**
 * The sole internal API a future distribution module (Feed/Matching,
 * P008-P009) will use to consume/restore quota units. No such caller
 * exists yet in this chantier — this contract is built and tested
 * standalone so it can be wired in later without changing its shape.
 *
 * docs/03-abonnements-et-classes-economiques-wasplex.md §4: "AdDelivered
 * consomme une unité du quota" / "QualifiedAttention déclenche la
 * rémunération" — two distinct events. This contract only ever moves the
 * quota counter; it has no notion of reward and must never be confused
 * with one.
 */
interface SubscriptionQuotaContract
{
    /**
     * @throws NoActiveSubscriptionException
     * @throws QuotaExhaustedException
     */
    public function consume(string $accountId, int $units, string $idempotencyKey): SubscriptionQuotaCounter;

    /**
     * @throws NoActiveSubscriptionException
     */
    public function restore(string $accountId, int $units, string $idempotencyKey): SubscriptionQuotaCounter;

    /**
     * @throws NoActiveSubscriptionException
     */
    public function currentCounter(string $accountId): SubscriptionQuotaCounter;
}
