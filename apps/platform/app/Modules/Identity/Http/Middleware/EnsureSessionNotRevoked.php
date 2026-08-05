<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Application\Services\AccountSessionManager;
use App\Shared\Http\ApiErrorResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Invariant (docs/chantiers/P001-CHANTIER.md): an application session can be
 * revoked independently from the underlying Laravel session. This runs on
 * every authenticated request and rejects a session already marked revoked.
 */
final class EnsureSessionNotRevoked
{
    public function __construct(private readonly AccountSessionManager $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $accountSession = $this->sessions->findByLaravelSessionId($request->session()->getId());

        if ($accountSession === null || $accountSession->isRevoked()) {
            Auth::logout();
            $request->session()->invalidate();

            return ApiErrorResponse::make($request, 'SESSION_REVOKED', 'Cette session a été révoquée.', status: 401);
        }

        $this->sessions->touch($accountSession);
        $request->attributes->set('account_session', $accountSession);

        return $next($request);
    }
}
