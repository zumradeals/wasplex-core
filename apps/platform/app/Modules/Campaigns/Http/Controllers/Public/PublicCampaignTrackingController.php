<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Http\Controllers\Public;

use App\Modules\Campaigns\Application\Services\PublicCampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class PublicCampaignTrackingController extends Controller
{
    public function __construct(private readonly PublicCampaignService $campaigns) {}

    public function start(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate([
            'visitor_token' => ['required', 'string', 'min:16', 'max:128'],
            'visit_token' => ['required', 'string', 'min:16', 'max:128'],
        ]);

        $visit = $this->campaigns->startVisit($slug, $data['visitor_token'], $data['visit_token']);

        if ($visit === null) {
            return response()->json(['message' => 'Campagne publique indisponible.'], 404);
        }

        return response()->json(['visit_id' => $visit->id], 201);
    }

    public function complete(string $slug, string $visit): JsonResponse
    {
        $updated = $this->campaigns->completeVisit($slug, $visit);

        return $updated === null
            ? response()->json(['message' => 'Campagne publique indisponible.'], 404)
            : response()->json(['completed' => true]);
    }

    public function join(string $slug, string $visit): JsonResponse
    {
        $updated = $this->campaigns->recordJoinClick($slug, $visit);

        return $updated === null
            ? response()->json(['message' => 'Campagne publique indisponible.'], 404)
            : response()->json(['recorded' => true]);
    }

    public function share(string $slug, string $visit): JsonResponse
    {
        $updated = $this->campaigns->recordShare($slug, $visit);

        return $updated === null
            ? response()->json(['message' => 'Campagne publique indisponible.'], 404)
            : response()->json(['shares' => $updated->share_count]);
    }
}
