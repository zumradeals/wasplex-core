<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Http\Controllers\Admin;

use App\Modules\AdvertiserStudio\Application\Contracts\BrandDirectoryContract;
use App\Modules\AdvertiserStudio\Application\Contracts\CreativeAssetDirectoryContract;
use App\Modules\Campaigns\Application\Services\CampaignNotApprovedException;
use App\Modules\Campaigns\Application\Services\CampaignNotFoundException;
use App\Modules\Campaigns\Application\Services\CampaignReviewCaseAlreadyDecidedException;
use App\Modules\Campaigns\Application\Services\CampaignReviewCaseNotFoundException;
use App\Modules\Campaigns\Application\Services\CampaignReviewService;
use App\Modules\Campaigns\Application\Services\SameAdminCannotDecideException;
use App\Modules\Campaigns\Infrastructure\Models\Campaign;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * docs/13-studio-annonceur-wasplex.md §93 : file de revue administrative.
 * `{campaignReview}` désigne un id de campaign_review_cases.
 */
final class CampaignReviewsController extends Controller
{
    public function __construct(
        private readonly CampaignReviewService $reviews,
        private readonly BrandDirectoryContract $brands,
        private readonly CreativeAssetDirectoryContract $creativeAssets,
    ) {}

    public function index(): JsonResponse
    {
        $cases = $this->reviews->listQueue()->map(function ($case) {
            $data = $case->toArray();
            $data['brand'] = $this->brands->find($case->campaign->organization_id, $case->campaign->brand_id);

            return $data;
        });

        return response()->json(['review_cases' => $cases]);
    }

    public function show(string $campaignReview): JsonResponse
    {
        try {
            $case = $this->reviews->find($campaignReview);
        } catch (CampaignReviewCaseNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }

        $campaign = $case->campaign;
        $version = $case->campaignVersion;
        $creative = $version->creative_configuration ?? [];

        $brand = $this->brands->find($campaign->organization_id, $campaign->brand_id);
        $asset = isset($creative['asset_id'])
            ? $this->creativeAssets->find($campaign->organization_id, $creative['asset_id'])
            : null;

        return response()->json([
            'review_case' => $case,
            'brand' => $brand,
            'asset' => $asset,
        ]);
    }

    public function approve(Request $request, string $campaignReview): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        try {
            $case = $this->reviews->approve($campaignReview, $account->id);
        } catch (CampaignReviewCaseNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (CampaignReviewCaseAlreadyDecidedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (SameAdminCannotDecideException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        return response()->json(['review_case' => $case]);
    }

    public function requestChanges(Request $request, string $campaignReview): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:1']]);

        /** @var Account $account */
        $account = $request->user();

        try {
            $case = $this->reviews->requestChanges($campaignReview, $account->id, $data['reason']);
        } catch (CampaignReviewCaseNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (CampaignReviewCaseAlreadyDecidedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['review_case' => $case]);
    }

    public function reject(Request $request, string $campaignReview): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:1']]);

        /** @var Account $account */
        $account = $request->user();

        try {
            $case = $this->reviews->reject($campaignReview, $account->id, $data['reason']);
        } catch (CampaignReviewCaseNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (CampaignReviewCaseAlreadyDecidedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (SameAdminCannotDecideException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        return response()->json(['review_case' => $case]);
    }

    /**
     * Not part of docs/13 §93's exact route list, but necessary for the
     * admin to locate a campaign to suspend — suspend() needs a target
     * and no other endpoint surfaces approved campaigns.
     */
    public function approved(): JsonResponse
    {
        $campaigns = Campaign::query()
            ->where('status', Campaign::STATUS_APPROVED)
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (Campaign $campaign) {
                $data = $campaign->toArray();
                $data['brand'] = $this->brands->find($campaign->organization_id, $campaign->brand_id);

                return $data;
            });

        return response()->json(['campaigns' => $campaigns]);
    }

    public function suspend(Request $request, string $campaign): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:1']]);

        /** @var Account $account */
        $account = $request->user();

        try {
            $updated = $this->reviews->suspend($campaign, $account->id, $data['reason']);
        } catch (CampaignNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (CampaignNotApprovedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['campaign' => $updated]);
    }
}
