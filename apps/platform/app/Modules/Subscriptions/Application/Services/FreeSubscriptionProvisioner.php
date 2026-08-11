<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Services;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Subscriptions\Infrastructure\Models\EconomicClass;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionEntitlement;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionEvent;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPlanVersion;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the member subscription baseline healthy:
 * - an account with no active subscription receives the published FREE plan;
 * - historical duplicate active subscriptions are reduced to one;
 * - the monthly advertising/feed quota is initialized immediately.
 */
final class FreeSubscriptionProvisioner
{
    public function __construct(private readonly SubscriptionQuotaContract $quota) {}

    public function ensureFor(string $accountId): ?UserSubscription
    {
        $subscription = DB::transaction(function () use ($accountId): ?UserSubscription {
            // Serialize provisioning on the stable account row so concurrent
            // /current and /quota requests cannot create two FREE records.
            Account::query()->whereKey($accountId)->lockForUpdate()->firstOrFail();

            $active = UserSubscription::query()
                ->where('account_id', $accountId)
                ->whereIn('status', [UserSubscription::STATUS_ACTIVE, UserSubscription::STATUS_SCHEDULED_DOWNGRADE])
                ->orderByDesc('started_at')
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->get();

            if ($active->isNotEmpty()) {
                /** @var UserSubscription $keep */
                $keep = $active->first();

                foreach ($active->slice(1) as $duplicate) {
                    $duplicate->update([
                        'status' => UserSubscription::STATUS_CANCELLED,
                        'cancelled_at' => now(),
                    ]);

                    SubscriptionEvent::query()->create([
                        'user_subscription_id' => $duplicate->id,
                        'event_type' => SubscriptionEvent::TYPE_SUSPENDED,
                        'payload' => ['reason' => 'duplicate_active_subscription_cleanup'],
                    ]);
                }

                return $keep->refresh();
            }

            $freePlan = SubscriptionPlanVersion::query()
                ->where('status', SubscriptionPlanVersion::STATUS_PUBLISHED)
                ->where('price_minor', 0)
                ->whereHas('economicClassLink.economicClass', fn ($query) => $query->where('code', EconomicClass::CODE_FREE))
                ->with('economicClassLink.economicClass')
                ->orderByDesc('effective_from')
                ->first();

            if ($freePlan === null || $freePlan->economicClassLink === null) {
                return null;
            }

            $created = UserSubscription::query()->create([
                'account_id' => $accountId,
                'plan_version_id' => $freePlan->id,
                'economic_class_id' => $freePlan->economicClassLink->economic_class_id,
                'status' => UserSubscription::STATUS_ACTIVE,
                'started_at' => now(),
                // FREE is the permanent fallback. Its quota still resets on
                // calendar-month cycles through SubscriptionQuotaService.
                'current_period_end' => null,
            ]);

            SubscriptionEvent::query()->create([
                'user_subscription_id' => $created->id,
                'event_type' => SubscriptionEvent::TYPE_SELECTED,
                'payload' => ['plan_version_id' => $freePlan->id, 'automatic' => true],
            ]);

            SubscriptionEvent::query()->create([
                'user_subscription_id' => $created->id,
                'event_type' => SubscriptionEvent::TYPE_ACTIVATED,
                'payload' => ['plan_version_id' => $freePlan->id, 'automatic' => true],
            ]);

            SubscriptionEntitlement::query()->updateOrCreate(
                ['user_subscription_id' => $created->id, 'key' => SubscriptionEntitlement::KEY_FONDS_ELIGIBLE],
                ['enabled' => false],
            );

            return $created;
        });

        if ($subscription !== null && $subscription->isActive()) {
            $this->quota->currentCounter($accountId);
        }

        return $subscription;
    }
}
