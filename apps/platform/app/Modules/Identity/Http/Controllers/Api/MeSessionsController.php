<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\Services\AccountSessionManager;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\AccountSession;
use App\Shared\Http\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MeSessionsController extends Controller
{
    public function __construct(private readonly AccountSessionManager $sessions) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();
        $currentSessionId = $request->session()->getId();

        $sessions = $account->sessions()
            ->whereNull('revoked_at')
            ->orderByDesc('last_active_at')
            ->get();

        return new JsonResponse([
            'sessions' => $sessions->map(fn (AccountSession $s) => [
                'id' => $s->id,
                'ip_address' => $s->ip_address,
                'user_agent' => $s->user_agent,
                'last_active_at' => $s->last_active_at->toIso8601String(),
                'is_current' => $s->laravel_session_id === $currentSessionId,
            ])->values(),
        ]);
    }

    public function destroy(Request $request, string $session): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        /** @var AccountSession|null $accountSession */
        $accountSession = $account->sessions()->whereKey($session)->first();

        if ($accountSession === null) {
            return ApiErrorResponse::make($request, 'SESSION_NOT_FOUND', 'Session introuvable.', status: 404);
        }

        $this->sessions->revoke($accountSession);

        return new JsonResponse(status: 204);
    }
}
