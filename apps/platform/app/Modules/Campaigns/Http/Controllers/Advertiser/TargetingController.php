<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Http\Controllers\Advertiser;

use App\Modules\SmartProfile\Application\Contracts\ProfileTargetingContract;
use App\Modules\Subscriptions\Application\Contracts\EconomicClassCatalogContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Read-only lookup for the Audience wizard step (docs/13 §36-38) — never
 * exposes only the subscription level code and its direct reward.
 */
final class TargetingController extends Controller
{
    public function __construct(
        private readonly EconomicClassCatalogContract $economicClasses,
        private readonly ProfileTargetingContract $profileTargeting,
    ) {}

    public function economicClasses(): JsonResponse
    {
        $classes = array_map(fn ($summary) => [
            'code' => $summary->code,
            'reward_per_complete_view_minor' => $summary->rewardPerCompleteViewMinor,
        ], $this->economicClasses->listActive());

        return response()->json(['economic_classes' => $classes]);
    }

    public function profileCriteria(): JsonResponse
    {
        return response()->json(['profile_criteria' => $this->profileTargeting->targetableCatalog()]);
    }
}
