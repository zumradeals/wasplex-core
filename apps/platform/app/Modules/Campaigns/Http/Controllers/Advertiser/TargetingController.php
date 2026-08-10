<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Http\Controllers\Advertiser;

use App\Modules\SmartProfile\Application\Contracts\ProfileTargetingContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Read-only lookup for advertiser-facing audience criteria. Subscription
 * classes are deliberately not exposed: they are an internal Wasplex concern.
 */
final class TargetingController extends Controller
{
    public function __construct(private readonly ProfileTargetingContract $profileTargeting) {}

    public function profileCriteria(): JsonResponse
    {
        return response()->json(['profile_criteria' => $this->profileTargeting->targetableCatalog()]);
    }
}
