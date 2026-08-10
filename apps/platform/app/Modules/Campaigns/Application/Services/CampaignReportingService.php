<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use App\Modules\Campaigns\Application\Contracts\CampaignsReportingContract;
use App\Modules\Campaigns\Application\ValueObjects\CampaignReport;
use App\Modules\Campaigns\Application\ValueObjects\CampaignsBudgetSummary;
use App\Modules\Campaigns\Infrastructure\Models\Campaign;
use App\Modules\Campaigns\Infrastructure\Models\CampaignBudgetReservation;
use App\Modules\Campaigns\Infrastructure\Models\CampaignEnvelopeConsumption;
use App\Modules\Feed\Application\Contracts\FeedCampaignStatsContract;
use Illuminate\Support\Collection;

final class CampaignReportingService implements CampaignsReportingContract
{
    public function __construct(private readonly FeedCampaignStatsContract $feedStats) {}

    public function reportFor(string $organizationId, string $campaignId): ?CampaignReport
    {
        $campaign = Campaign::query()->where('organization_id', $organizationId)->find($campaignId);

        if ($campaign === null) {
            return null;
        }

        $consumptionTotals = $this->consumptionTotalsFor([$campaign->id]);
        $reservedBudget = (int) $campaign->reservations()->where('status', CampaignBudgetReservation::STATUS_RESERVED)->sum('amount_minor');

        return $this->buildReport($campaign, $reservedBudget, $consumptionTotals->get($campaign->id, collect()));
    }

    /**
     * @return array<int, CampaignReport>
     */
    public function reportForOrganization(string $organizationId): array
    {
        $campaigns = Campaign::query()->where('organization_id', $organizationId)->get();

        if ($campaigns->isEmpty()) {
            return [];
        }

        $campaignIds = $campaigns->pluck('id')->all();
        $consumptionTotals = $this->consumptionTotalsFor($campaignIds);

        $reservedByCampaign = CampaignBudgetReservation::query()
            ->whereIn('campaign_id', $campaignIds)
            ->where('status', CampaignBudgetReservation::STATUS_RESERVED)
            ->selectRaw('campaign_id, coalesce(sum(amount_minor), 0) as total')
            ->groupBy('campaign_id')
            ->pluck('total', 'campaign_id');

        return $campaigns
            ->map(fn (Campaign $campaign): CampaignReport => $this->buildReport(
                $campaign,
                (int) ($reservedByCampaign[$campaign->id] ?? 0),
                $consumptionTotals->get($campaign->id, collect()),
            ))
            ->all();
    }

    public function globalBudgetSummary(): CampaignsBudgetSummary
    {
        $active = Campaign::query()->where('status', Campaign::STATUS_APPROVED)->count();

        $totalReserved = (int) CampaignBudgetReservation::query()
            ->where('status', CampaignBudgetReservation::STATUS_RESERVED)
            ->sum('amount_minor');

        $consumptionSums = CampaignEnvelopeConsumption::query()
            ->selectRaw('status, coalesce(sum(gain_minor), 0) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return new CampaignsBudgetSummary(
            activeCampaigns: $active,
            totalReservedMinor: $totalReserved,
            totalCapturedMinor: ((int) ($consumptionSums[CampaignEnvelopeConsumption::STATUS_CAPTURED] ?? 0)) * 2,
            totalReleasedMinor: (int) ($consumptionSums[CampaignEnvelopeConsumption::STATUS_RELEASED] ?? 0),
        );
    }

    /**
     * Envelope consumptions are linked via campaign_quote_id -> campaign
     * version -> campaign, not campaign_id directly (docs/chantiers/
     * P012-CHANTIER.md §4) — grouped here once per batch of campaigns.
     *
     * @param  array<int, string>  $campaignIds
     * @return Collection<string, Collection<int, object{status: string, total: int}>>
     */
    private function consumptionTotalsFor(array $campaignIds): Collection
    {
        if ($campaignIds === []) {
            return collect();
        }

        return CampaignEnvelopeConsumption::query()
            ->join('campaign_quotes', 'campaign_quotes.id', '=', 'campaign_envelope_consumptions.campaign_quote_id')
            ->join('campaign_versions', 'campaign_versions.id', '=', 'campaign_quotes.campaign_version_id')
            ->whereIn('campaign_versions.campaign_id', $campaignIds)
            ->selectRaw('campaign_versions.campaign_id as campaign_id, campaign_envelope_consumptions.status as status, coalesce(sum(campaign_envelope_consumptions.gain_minor), 0) as total')
            ->groupBy('campaign_versions.campaign_id', 'campaign_envelope_consumptions.status')
            ->get()
            ->groupBy('campaign_id');
    }

    /**
     * @param  Collection<int, object{status: string, total: int}>  $consumptionRows
     */
    private function buildReport(Campaign $campaign, int $reservedBudgetMinor, Collection $consumptionRows): CampaignReport
    {
        $byStatus = $consumptionRows->pluck('total', 'status');

        // Campaign::budget_amount_minor is never populated by CampaignService
        // (docs/chantiers/P006-CHANTIER.md) — the real target budget lives
        // in the current version's budget_configuration.
        $targetBudgetMinor = (int) ($campaign->currentVersion()?->budget_configuration['budget_amount_minor'] ?? 0);

        return new CampaignReport(
            campaignId: $campaign->id,
            organizationId: $campaign->organization_id,
            status: $campaign->status,
            budgetAmountMinor: $targetBudgetMinor,
            budgetReservedMinor: $reservedBudgetMinor,
            budgetCapturedMinor: ((int) ($byStatus[CampaignEnvelopeConsumption::STATUS_CAPTURED] ?? 0)) * 2,
            budgetReleasedMinor: (int) ($byStatus[CampaignEnvelopeConsumption::STATUS_RELEASED] ?? 0),
            feed: $this->feedStats->statsForCampaign($campaign->id),
        );
    }
}
