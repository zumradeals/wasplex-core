<?php

declare(strict_types=1);

namespace App\Modules\Matching\Http\Controllers\Admin;

use App\Modules\Matching\Application\Services\MatchingConfigurationNotDraftException;
use App\Modules\Matching\Application\Services\MatchingConfigurationNotFoundException;
use App\Modules\Matching\Application\Services\MatchingConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class MatchingConfigurationController extends Controller
{
    public function __construct(private readonly MatchingConfigurationService $configurations) {}

    public function index(): JsonResponse
    {
        return response()->json(['configurations' => $this->configurations->listAll()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'frequency_window_hours' => ['required', 'integer', 'min:1'],
            'frequency_max_per_window' => ['required', 'integer', 'min:1'],
            'fatigue_threshold' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json(['configuration' => $this->configurations->createDraft($data)], 201);
    }

    public function update(Request $request, string $configuration): JsonResponse
    {
        $data = $request->validate([
            'frequency_window_hours' => ['sometimes', 'integer', 'min:1'],
            'frequency_max_per_window' => ['sometimes', 'integer', 'min:1'],
            'fatigue_threshold' => ['sometimes', 'integer', 'min:1'],
        ]);

        try {
            $updated = $this->configurations->updateDraft($configuration, $data);
        } catch (MatchingConfigurationNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (MatchingConfigurationNotDraftException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['configuration' => $updated]);
    }

    public function publish(string $configuration): JsonResponse
    {
        try {
            $published = $this->configurations->publish($configuration);
        } catch (MatchingConfigurationNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }

        return response()->json(['configuration' => $published]);
    }
}
