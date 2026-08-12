<?php

declare(strict_types=1);

namespace App\Modules\Feed\Http\Controllers\User;

use App\Modules\Feed\Application\Services\FeedPreloadService;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class FeedPreloadController extends Controller
{
    public function __construct(private readonly FeedPreloadService $preload) {}

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_campaign_id' => ['nullable', 'string'],
        ]);

        /** @var Account $account */
        $account = $request->user();

        return response()->json([
            'preload' => $this->preload->peek(
                $account->id,
                $data['current_campaign_id'] ?? null,
            ),
        ]);
    }
}
