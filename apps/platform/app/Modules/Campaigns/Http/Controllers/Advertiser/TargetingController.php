<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Http\Controllers\Advertiser;

use App\Modules\Subscriptions\Application\Contracts\EconomicClassCatalogContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Read-only lookup for the Audience wizard step (docs/13 §36-38) — never
 * exposes anything beyond code/weight/coefficient, the same projection
 * used internally to compute the quote.
 */
final class TargetingController extends Controller
{
    public function __construct(private readonly EconomicClassCatalogContract $economicClasses) {}

    public function economicClasses(): JsonResponse
    {
        $classes = array_map(fn ($summary) => [
            'code' => $summary->code,
            'weight_percent' => $summary->weightPercent,
        ], $this->economicClasses->listActive());

        return response()->json(['economic_classes' => $classes]);
    }
}
