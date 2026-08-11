<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Http\Controllers\Advertiser;

use App\Modules\Campaigns\Application\Services\CampaignNotFoundException;
use App\Modules\Campaigns\Application\Services\PublicCampaignService;
use App\Modules\Campaigns\Infrastructure\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class CampaignDistributionController extends Controller
{
    public function __construct(
        private readonly PublicCampaignService $publicCampaigns,
    ) {}

    public function update(Request $request, string $campaign): JsonResponse
    {
        $organizationId = (string) $request->attributes->get('advertiser_organization_id');
        $data = $request->validate([
            'distribution_mode' => ['required', 'string', Rule::in([
                Campaign::DISTRIBUTION_MEMBERS_ONLY,
                Campaign::DISTRIBUTION_MEMBERS_PUBLIC,
            ])],
        ]);

        $model = Campaign::query()
            ->where('organization_id', $organizationId)
            ->with('versions')
            ->find($campaign);

        if ($model === null) {
            return response()->json(['message' => 'Campagne introuvable.'], 404);
        }

        if (
            $data['distribution_mode'] === Campaign::DISTRIBUTION_MEMBERS_PUBLIC
            && $model->status !== Campaign::STATUS_APPROVED
        ) {
            return response()->json([
                'message' => 'La portée publique peut être activée après approbation de la publicité.',
            ], 422);
        }

        $updates = ['distribution_mode' => $data['distribution_mode']];

        if ($data['distribution_mode'] === Campaign::DISTRIBUTION_MEMBERS_PUBLIC && $model->public_slug === null) {
            $version = $model->currentVersion();
            $title = trim((string) ($version?->creative_configuration['title'] ?? ''));
            $base = Str::slug($title) ?: 'publicite';
            $updates['public_slug'] = Str::limit($base, 70, '').'-'.Str::lower($model->id);
        }

        $model->update($updates);
        $model->refresh()->load('versions');

        return response()->json([
            'campaign' => $model,
            'public_url' => $model->distribution_mode === Campaign::DISTRIBUTION_MEMBERS_PUBLIC
                ? $this->publicCampaigns->publicUrl($model)
                : null,
        ]);
    }

    public function report(Request $request, string $campaign): JsonResponse
    {
        $organizationId = (string) $request->attributes->get('advertiser_organization_id');

        $model = Campaign::query()
            ->where('organization_id', $organizationId)
            ->find($campaign);

        if ($model === null) {
            return response()->json(['message' => 'Campagne introuvable.'], 404);
        }

        try {
            $stats = $this->publicCampaigns->stats($organizationId, $model->id);
        } catch (CampaignNotFoundException) {
            return response()->json(['message' => 'Campagne introuvable.'], 404);
        }

        return response()->json([
            'public_report' => [
                'status' => $model->status,
                'distribution_mode' => $model->distribution_mode,
                'public_url' => $model->distribution_mode === Campaign::DISTRIBUTION_MEMBERS_PUBLIC
                    ? $this->publicCampaigns->publicUrl($model)
                    : null,
                'stats' => $stats,
            ],
        ]);
    }
}
