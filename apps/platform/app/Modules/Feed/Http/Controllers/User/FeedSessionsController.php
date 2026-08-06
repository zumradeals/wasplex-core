<?php

declare(strict_types=1);

namespace App\Modules\Feed\Http\Controllers\User;

use App\Modules\Feed\Application\Services\FeedSessionService;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class FeedSessionsController extends Controller
{
    public function __construct(private readonly FeedSessionService $sessions) {}

    public function store(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        $session = $this->sessions->start($account->id);

        return response()->json(['feed_session' => $session], 201);
    }
}
