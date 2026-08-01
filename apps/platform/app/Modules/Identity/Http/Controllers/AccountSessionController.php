<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Services\AccessAuditor;
use App\Modules\Identity\Application\Services\AccountSessionService;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\AccountSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class AccountSessionController
{
    public function __construct(
        private AccountSessionService $sessions,
        private AccessAuditor $auditor,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();
        $current = $request->attributes->get('identity_session');

        return response()->json([
            'data' => $account->sessions()->with('device')->latest('last_seen_at')->get()->map(
                fn (AccountSession $session): array => [
                    'id' => $session->id,
                    'device' => $session->device?->name,
                    'platform' => $session->device?->platform,
                    'last_seen_at' => $session->last_seen_at->toIso8601String(),
                    'revoked_at' => $session->revoked_at?->toIso8601String(),
                    'current' => $session->id === $current?->id,
                ],
            ),
        ]);
    }

    public function destroy(Request $request, AccountSession $accountSession): JsonResponse
    {
        abort_unless($accountSession->account_id === $request->user()?->getAuthIdentifier(), 404);

        $this->sessions->revoke($accountSession);
        $this->auditor->record(
            $request,
            'session.revoked',
            'success',
            context: ['revoked_session_id' => $accountSession->id],
        );

        return response()->json(['data' => ['revoked' => true]]);
    }
}
