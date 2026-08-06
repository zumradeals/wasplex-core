<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Http\Controllers\Admin;

use App\Modules\AdvertiserStudio\Application\Services\CreativeLibraryService;
use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

final class CreativeModerationController extends Controller
{
    public function __construct(private readonly CreativeLibraryService $library) {}

    public function index(): JsonResponse
    {
        $assets = CreativeAsset::query()
            ->whereIn('moderation_status', [CreativeAsset::STATUS_READY, CreativeAsset::STATUS_NEEDS_CHANGES])
            ->orderBy('created_at')
            ->get();

        return response()->json(['assets' => $assets]);
    }

    public function decide(Request $request, string $asset): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'request_changes', 'reject'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $found = CreativeAsset::query()->findOrFail($asset);
        $updated = $this->library->moderate($found, $data['decision'], (string) $request->user()->id, $data['reason'] ?? null);

        return response()->json(['asset' => $updated]);
    }
}
