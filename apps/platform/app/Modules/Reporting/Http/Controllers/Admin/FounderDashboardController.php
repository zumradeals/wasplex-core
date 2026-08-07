<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Controllers\Admin;

use App\Modules\Reporting\Application\Services\FounderDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class FounderDashboardController extends Controller
{
    public function __construct(private readonly FounderDashboardService $dashboard) {}

    public function show(): JsonResponse
    {
        $summary = $this->dashboard->summary();

        return response()->json([
            'ledger' => [
                'total_debit_minor' => $summary->ledger->totalDebitMinor,
                'total_credit_minor' => $summary->ledger->totalCreditMinor,
                'is_balanced' => $summary->ledger->isBalanced(),
                'transaction_count' => $summary->ledger->transactionCount,
                'net_by_account_type' => $summary->ledger->netByAccountType,
            ],
            'feed' => [
                'reserved' => $summary->feed->reserved,
                'started' => $summary->feed->started,
                'completed' => $summary->feed->completed,
                'abandoned' => $summary->feed->abandoned,
                'expired' => $summary->feed->expired,
                'held' => $summary->feed->held,
                'rejected' => $summary->feed->rejected,
                'total_deliveries' => $summary->feed->totalDeliveries(),
                'gain_distributed_minor' => $summary->feed->gainDistributedMinor,
                'attention_rate' => round($summary->feed->attentionRate(), 4),
            ],
            'campaigns' => [
                'active_campaigns' => $summary->campaigns->activeCampaigns,
                'total_reserved_minor' => $summary->campaigns->totalReservedMinor,
                'total_captured_minor' => $summary->campaigns->totalCapturedMinor,
                'total_released_minor' => $summary->campaigns->totalReleasedMinor,
            ],
            'subscriptions' => [
                'active_subscriptions' => $summary->subscriptions->activeSubscriptions,
                'total_revenue_minor' => $summary->subscriptions->totalRevenueMinor,
            ],
        ]);
    }
}
