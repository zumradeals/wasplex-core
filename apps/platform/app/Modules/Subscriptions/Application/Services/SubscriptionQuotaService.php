<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Services;

use App\Modules\Subscriptions\Infrastructure\Models\EconomicClassVersion;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionCycle;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionEvent;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionQuotaCounter;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class SubscriptionQuotaService implements SubscriptionQuotaContract
{
    public function consume(string $accountId, int $units, string $idempotencyKey): SubscriptionQuotaCounter
    {
        return DB::transaction(function () use ($accountId, $units, $idempotencyKey) {
            $subscription = $this->resolveActiveSubscription($accountId);
            $cycle = $this->ensureCycleForNow($subscription);

            $existing = SubscriptionEvent::query()
                ->where('user_subscription_id', $subscription->id)
                ->where('event_type', SubscriptionEvent::TYPE_QUOTA_CONSUMED)
                ->where('idempotency_key', $idempotencyKey)
                ->exists();

            /** @var SubscriptionQuotaCounter $counter */
            $counter = SubscriptionQuotaCounter::query()
                ->where('subscription_cycle_id', $cycle->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($existing) {
                return $counter;
            }

            if ($counter->remaining() < $units) {
                throw new QuotaExhaustedException($accountId);
            }

            $counter->update(['quota_consumed' => $counter->quota_consumed + $units]);

            SubscriptionEvent::query()->create([
                'user_subscription_id' => $subscription->id,
                'event_type' => SubscriptionEvent::TYPE_QUOTA_CONSUMED,
                'payload' => ['units' => $units],
                'idempotency_key' => $idempotencyKey,
            ]);

            return $counter->refresh();
        });
    }

    public function restore(string $accountId, int $units, string $idempotencyKey): SubscriptionQuotaCounter
    {
        return DB::transaction(function () use ($accountId, $units, $idempotencyKey) {
            $subscription = $this->resolveActiveSubscription($accountId);
            $cycle = $this->ensureCycleForNow($subscription);

            $existing = SubscriptionEvent::query()
                ->where('user_subscription_id', $subscription->id)
                ->where('event_type', SubscriptionEvent::TYPE_QUOTA_RESTORED)
                ->where('idempotency_key', $idempotencyKey)
                ->exists();

            /** @var SubscriptionQuotaCounter $counter */
            $counter = SubscriptionQuotaCounter::query()
                ->where('subscription_cycle_id', $cycle->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($existing) {
                return $counter;
            }

            $restored = min($units, $counter->quota_consumed);
            $counter->update(['quota_consumed' => $counter->quota_consumed - $restored]);

            SubscriptionEvent::query()->create([
                'user_subscription_id' => $subscription->id,
                'event_type' => SubscriptionEvent::TYPE_QUOTA_RESTORED,
                'payload' => ['units' => $restored],
                'idempotency_key' => $idempotencyKey,
            ]);

            return $counter->refresh();
        });
    }

    public function currentCounter(string $accountId): SubscriptionQuotaCounter
    {
        $subscription = $this->resolveActiveSubscription($accountId);
        $cycle = $this->ensureCycleForNow($subscription);

        return SubscriptionQuotaCounter::query()->where('subscription_cycle_id', $cycle->id)->firstOrFail();
    }

    private function resolveActiveSubscription(string $accountId): UserSubscription
    {
        $subscription = UserSubscription::query()
            ->where('account_id', $accountId)
            ->whereIn('status', [UserSubscription::STATUS_ACTIVE, UserSubscription::STATUS_SCHEDULED_DOWNGRADE])
            ->first();

        if ($subscription === null) {
            throw new NoActiveSubscriptionException($accountId);
        }

        return $subscription;
    }

    /**
     * docs/03 §8: "cycle mensuel civil... remise à zéro en début de
     * cycle... remise à zéro idempotente". Calendar-month boundaries in
     * UTC — Identity does not yet store a per-account timezone, so the
     * "timezone du pays principal de l'utilisateur" refinement from §8 is
     * deferred (documented limit in P004-RAPPORT.md).
     */
    private function ensureCycleForNow(UserSubscription $subscription): SubscriptionCycle
    {
        $now = Carbon::now('UTC');
        $periodStart = $now->clone()->startOfMonth();
        $periodEnd = $now->clone()->addMonthNoOverflow()->startOfMonth();

        $cycle = SubscriptionCycle::query()
            ->where('user_subscription_id', $subscription->id)
            ->where('period_start', $periodStart)
            ->first();

        if ($cycle !== null) {
            return $cycle;
        }

        $quota = EconomicClassVersion::query()
            ->where('economic_class_id', $subscription->economic_class_id)
            ->where('effective_from', '<=', $now)
            ->where(function ($query) use ($now): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $now);
            })
            ->orderByDesc('effective_from')
            ->value('quota_monthly');

        try {
            return DB::transaction(function () use ($subscription, $periodStart, $periodEnd, $now, $quota) {
                $cycle = SubscriptionCycle::query()->create([
                    'user_subscription_id' => $subscription->id,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'reset_at' => $now,
                ]);

                SubscriptionQuotaCounter::query()->create([
                    'subscription_cycle_id' => $cycle->id,
                    'quota_allocated' => $quota ?? 0,
                    'quota_consumed' => 0,
                ]);

                SubscriptionEvent::query()->create([
                    'user_subscription_id' => $subscription->id,
                    'event_type' => SubscriptionEvent::TYPE_QUOTA_RESET,
                    'payload' => ['period_start' => $periodStart->toIso8601String(), 'quota_allocated' => $quota ?? 0],
                    'idempotency_key' => "reset:{$subscription->id}:{$periodStart->toDateString()}",
                ]);

                return $cycle;
            });
        } catch (QueryException $e) {
            if (! str_contains($e->getMessage(), 'subscription_cycles_user_subscription_id_period_start_unique')) {
                throw $e;
            }

            // Another request won the race to reset this cycle first
            // (docs/03 §8: "remise à zéro idempotente") — use its cycle.
            return SubscriptionCycle::query()
                ->where('user_subscription_id', $subscription->id)
                ->where('period_start', $periodStart)
                ->firstOrFail();
        }
    }
}
