<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Http\Controllers\Advertiser;

use App\Modules\AdvertiserWallet\Application\Services\InsufficientAdvertiserBalanceException;
use App\Modules\Campaigns\Application\Services\CampaignBrandNotFoundException;
use App\Modules\Campaigns\Application\Services\CampaignNotFoundException;
use App\Modules\Campaigns\Application\Services\CampaignService;
use App\Modules\Campaigns\Application\Services\InvalidCampaignConfigurationException;
use App\Modules\Campaigns\Application\Services\InvalidCampaignStateException;
use App\Modules\Campaigns\Application\Services\NoPublishedPriceCatalogException;
use App\Modules\Campaigns\Application\Services\QuoteExpiredException;
use App\Modules\Campaigns\Application\Services\SegmentTooSmallException;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * docs/13-studio-annonceur-wasplex.md §90 (sous-ensemble pertinent à la
 * campagne rapide, docs/chantiers/P006-CHANTIER.md).
 */
final class CampaignsController extends Controller
{
    public function __construct(private readonly CampaignService $campaigns) {}

    public function index(Request $request): JsonResponse
    {
        $organizationId = $request->attributes->get('advertiser_organization_id');

        return response()->json(['campaigns' => $this->campaigns->list($organizationId)]);
    }

    public function store(Request $request): JsonResponse
    {
        $organizationId = $request->attributes->get('advertiser_organization_id');
        $data = $request->validate(['brand_id' => ['required', 'string']]);

        /** @var Account $account */
        $account = $request->user();

        try {
            $campaign = $this->campaigns->create($organizationId, $data['brand_id'], $account->id);
        } catch (CampaignBrandNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }

        return response()->json(['campaign' => $campaign], 201);
    }

    public function show(Request $request, string $campaign): JsonResponse
    {
        $organizationId = $request->attributes->get('advertiser_organization_id');

        try {
            return response()->json(['campaign' => $this->campaigns->find($organizationId, $campaign)]);
        } catch (CampaignNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }
    }

    public function update(Request $request, string $campaign): JsonResponse
    {
        $organizationId = $request->attributes->get('advertiser_organization_id');

        $data = $request->validate([
            'objective_code' => ['sometimes', 'nullable', 'string'],
            'creative_configuration' => ['sometimes', 'array'],
            'audience_configuration' => ['sometimes', 'array'],
            'budget_configuration' => ['sometimes', 'array'],
        ]);

        try {
            return response()->json(['campaign' => $this->campaigns->updateVersion($organizationId, $campaign, $data)]);
        } catch (CampaignNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (InvalidCampaignConfigurationException|InvalidCampaignStateException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function estimateAudience(Request $request, string $campaign): JsonResponse
    {
        $organizationId = $request->attributes->get('advertiser_organization_id');

        try {
            $estimate = $this->campaigns->estimateAudience($organizationId, $campaign);
        } catch (CampaignNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (InvalidCampaignConfigurationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'estimate' => [
                'estimated_min' => $estimate->estimatedMin,
                'estimated_max' => $estimate->estimatedMax,
                'too_small' => $estimate->tooSmall,
            ],
        ]);
    }

    public function quote(Request $request, string $campaign): JsonResponse
    {
        $organizationId = $request->attributes->get('advertiser_organization_id');

        try {
            $updated = $this->campaigns->quote($organizationId, $campaign);
        } catch (CampaignNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (InvalidCampaignConfigurationException|InvalidCampaignStateException|NoPublishedPriceCatalogException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (SegmentTooSmallException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'estimated_total' => $exception->estimatedTotal], 422);
        }

        return response()->json(['campaign' => $updated]);
    }

    public function fund(Request $request, string $campaign): JsonResponse
    {
        $organizationId = $request->attributes->get('advertiser_organization_id');

        try {
            $updated = $this->campaigns->fund($organizationId, $campaign);
        } catch (CampaignNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (InvalidCampaignStateException|QuoteExpiredException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (InsufficientAdvertiserBalanceException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'available_minor' => $exception->availableMinor,
                'requested_minor' => $exception->requestedMinor,
            ], 422);
        }

        return response()->json(['campaign' => $updated]);
    }

    public function submit(Request $request, string $campaign): JsonResponse
    {
        $organizationId = $request->attributes->get('advertiser_organization_id');

        try {
            $updated = $this->campaigns->submit($organizationId, $campaign);
        } catch (CampaignNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (InvalidCampaignStateException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['campaign' => $updated]);
    }
}
