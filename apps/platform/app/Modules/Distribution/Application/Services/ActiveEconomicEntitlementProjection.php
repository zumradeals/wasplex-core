<?php

namespace App\Modules\Distribution\Application\Services;

use App\Modules\Distribution\Domain\Exceptions\DistributionException;
use App\Modules\Identity\Infrastructure\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ActiveEconomicEntitlementProjection
{
    /**
     * @return array{
     *     subscriptionId:string,
     *     quotaCounterId:string,
     *     economicClassId:string,
     *     economicClassVersionId:string,
     *     economicClassCode:string,
     *     quotaLimit:int,
     *     consumed:int,
     *     remaining:int,
     *     cycleStart:string,
     *     cycleEnd:string
     * }
     */
    public function resolve(Account $account, bool $lock = false): array
    {
        $now = CarbonImmutable::now();
        $subscriptionQuery = DB::table('user_subscriptions')
            ->where('account_id', $account->id)
            ->where('status', 'active')
            ->where('starts_at', '<=', $now)
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', $now);
            })
            ->orderByDesc('starts_at');

        if ($lock) {
            $subscriptionQuery->lockForUpdate();
        }

        $subscription = $subscriptionQuery->first();

        if ($subscription === null) {
            throw new DistributionException('Aucune souscription économique active ne permet la livraison.');
        }

        $economicClass = DB::table('economic_classes')
            ->where('id', $subscription->economic_class_id)
            ->where('status', 'active')
            ->first();

        if ($economicClass === null) {
            throw new DistributionException('La classe économique active est introuvable.');
        }

        $version = DB::table('economic_class_versions')
            ->where('economic_class_id', $economicClass->id)
            ->where('state', 'published')
            ->where(function ($query) use ($now): void {
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $now);
            })
            ->orderByDesc('version')
            ->first();

        if ($version === null) {
            throw new DistributionException('Aucune version économique publiée ne permet la livraison.');
        }

        $cycleStart = $now->startOfMonth()->toDateString();
        $cycleEnd = $now->endOfMonth()->toDateString();

        DB::table('subscription_quota_counters')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'user_subscription_id' => $subscription->id,
            'cycle_start' => $cycleStart,
            'cycle_end' => $cycleEnd,
            'quota_limit' => (int) $version->quota_monthly,
            'consumed' => 0,
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $counterQuery = DB::table('subscription_quota_counters')
            ->where('user_subscription_id', $subscription->id)
            ->where('cycle_start', $cycleStart);

        if ($lock) {
            $counterQuery->lockForUpdate();
        }

        $counter = $counterQuery->first();

        if ($counter === null) {
            throw new DistributionException('Le compteur de quota actif ne peut pas être résolu.');
        }

        $remaining = max(0, (int) $counter->quota_limit - (int) $counter->consumed);

        return [
            'subscriptionId' => (string) $subscription->id,
            'quotaCounterId' => (string) $counter->id,
            'economicClassId' => (string) $economicClass->id,
            'economicClassVersionId' => (string) $version->id,
            'economicClassCode' => (string) $economicClass->code,
            'quotaLimit' => (int) $counter->quota_limit,
            'consumed' => (int) $counter->consumed,
            'remaining' => $remaining,
            'cycleStart' => (string) $counter->cycle_start,
            'cycleEnd' => (string) $counter->cycle_end,
        ];
    }
}
