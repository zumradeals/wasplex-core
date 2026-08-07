<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Http\Controllers\Advertiser;

use App\Modules\Campaigns\Application\Contracts\CampaignsReportingContract;
use App\Modules\Campaigns\Application\ValueObjects\CampaignReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * docs/18-reporting-statistiques-audit-observabilite-wasplex.md §24-26 :
 * reporting Studio Annonceur — self-service, jamais l'identité des
 * utilisateurs qui ont vu la publicité (docs/CLAUDE.md §11).
 */
final class CampaignReportingController extends Controller
{
    public function __construct(private readonly CampaignsReportingContract $reporting) {}

    public function show(Request $request, string $campaign): JsonResponse
    {
        $organizationId = $request->attributes->get('advertiser_organization_id');
        $report = $this->reporting->reportFor($organizationId, $campaign);

        if ($report === null) {
            return response()->json(['message' => 'Campagne introuvable.'], 404);
        }

        return response()->json(['report' => $this->format($report)]);
    }

    public function index(Request $request): JsonResponse
    {
        $organizationId = $request->attributes->get('advertiser_organization_id');
        $reports = $this->reporting->reportForOrganization($organizationId);

        return response()->json(['reports' => array_map($this->format(...), $reports)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function format(CampaignReport $report): array
    {
        return [
            'campaign_id' => $report->campaignId,
            'status' => $report->status,
            'budget_amount_minor' => $report->budgetAmountMinor,
            'budget_reserved_minor' => $report->budgetReservedMinor,
            'budget_captured_minor' => $report->budgetCapturedMinor,
            'budget_released_minor' => $report->budgetReleasedMinor,
            'feed' => [
                'reserved' => $report->feed->reserved,
                'started' => $report->feed->started,
                'completed' => $report->feed->completed,
                'abandoned' => $report->feed->abandoned,
                'expired' => $report->feed->expired,
                'held' => $report->feed->held,
                'rejected' => $report->feed->rejected,
                'total_deliveries' => $report->feed->totalDeliveries(),
                'gain_distributed_minor' => $report->feed->gainDistributedMinor,
                'attention_rate' => round($report->feed->attentionRate(), 4),
            ],
        ];
    }
}
