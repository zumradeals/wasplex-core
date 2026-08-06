<?php

declare(strict_types=1);

namespace App\Modules\Feed\Http\Controllers\Admin;

use App\Modules\Feed\Infrastructure\Models\FeedAdDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * docs/08-feed-principal-wasplex.md §61 : agrégats uniquement, aucune
 * identité de compte (même discipline que l'audit Matching, P008).
 */
final class FeedDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $counts = FeedAdDelivery::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $wpDistributed = (int) FeedAdDelivery::query()
            ->where('status', FeedAdDelivery::STATUS_COMPLETED)
            ->sum('gain_minor');

        return response()->json([
            'delivery_counts' => $counts,
            'wp_distributed_minor' => $wpDistributed,
        ]);
    }
}
