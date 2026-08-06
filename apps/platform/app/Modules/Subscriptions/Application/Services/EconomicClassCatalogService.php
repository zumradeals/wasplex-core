<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Services;

use App\Modules\Identity\Application\Contracts\AccountCountryLookupContract;
use App\Modules\Subscriptions\Application\Contracts\EconomicClassCatalogContract;
use App\Modules\Subscriptions\Application\ValueObjects\AudienceEstimate;
use App\Modules\Subscriptions\Application\ValueObjects\EconomicClassSummary;
use App\Modules\Subscriptions\Infrastructure\Models\EconomicClass;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use Illuminate\Support\Carbon;

final class EconomicClassCatalogService implements EconomicClassCatalogContract
{
    public function __construct(private readonly AccountCountryLookupContract $accountCountries) {}

    public function listActive(): array
    {
        $now = Carbon::now('UTC');

        return EconomicClass::query()
            ->where('status', 'active')
            ->with(['versions' => function ($query) use ($now): void {
                $query->where('effective_from', '<=', $now)
                    ->where(function ($q) use ($now): void {
                        $q->whereNull('effective_to')->orWhere('effective_to', '>', $now);
                    })
                    ->orderByDesc('effective_from')
                    ->limit(1);
            }])
            ->get()
            ->filter(fn (EconomicClass $class) => $class->versions->isNotEmpty())
            ->map(fn (EconomicClass $class) => new EconomicClassSummary(
                code: $class->code,
                weightPercent: (float) $class->versions->first()->weight_percent,
                coefficient: (float) $class->versions->first()->coefficient,
            ))
            ->values()
            ->all();
    }

    public function estimateAudience(array $classCodes, ?string $countryCode, int $minimumSegmentSize): AudienceEstimate
    {
        $classIds = EconomicClass::query()->whereIn('code', $classCodes)->pluck('id', 'code');

        $perClassCount = [];

        foreach ($classIds as $code => $economicClassId) {
            $accountIds = UserSubscription::query()
                ->where('economic_class_id', $economicClassId)
                ->whereIn('status', [UserSubscription::STATUS_ACTIVE, UserSubscription::STATUS_SCHEDULED_DOWNGRADE])
                ->pluck('account_id')
                ->all();

            $perClassCount[$code] = $countryCode === null
                ? count($accountIds)
                : $this->accountCountries->countInCountry($accountIds, $countryCode);
        }

        $total = array_sum($perClassCount);

        return new AudienceEstimate(
            estimatedMin: $this->bandLower($total),
            estimatedMax: $this->bandUpper($total),
            tooSmall: $total < $minimumSegmentSize,
            perClassCount: $perClassCount,
        );
    }

    public function classForAccount(string $accountId): ?string
    {
        return UserSubscription::query()
            ->where('account_id', $accountId)
            ->whereIn('status', [UserSubscription::STATUS_ACTIVE, UserSubscription::STATUS_SCHEDULED_DOWNGRADE])
            ->with('economicClass')
            ->latest('started_at')
            ->first()
            ?->economicClass
            ?->code;
    }

    /**
     * Rounds to a band of 5 so the advertiser never sees an exact
     * headcount (docs/13 §39: "Il ne fournit jamais une liste nominative").
     */
    private function bandLower(int $total): int
    {
        return $total === 0 ? 0 : (int) max(0, intdiv($total, 5) * 5);
    }

    private function bandUpper(int $total): int
    {
        return $total === 0 ? 0 : (int) ((intdiv($total, 5) + 1) * 5);
    }
}
