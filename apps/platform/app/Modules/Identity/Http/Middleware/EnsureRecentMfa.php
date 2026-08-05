<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Infrastructure\Models\AccountSession;
use App\Shared\Http\ApiErrorResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Invariant: la console d'administration exige une preuve MFA récente.
 * Docs/12-administration-centrale-supervision-fondateur-wasplex.md #72.
 */
final class EnsureRecentMfa
{
    private const RECENT_WINDOW_MINUTES = 15;

    public function handle(Request $request, Closure $next): Response
    {
        /** @var AccountSession|null $accountSession */
        $accountSession = $request->attributes->get('account_session');

        if ($accountSession !== null && $accountSession->hasRecentMfa(self::RECENT_WINDOW_MINUTES)) {
            return $next($request);
        }

        if ($request->is('api/*')) {
            return ApiErrorResponse::make(
                $request,
                'MFA_REQUIRED',
                'Une vérification MFA récente est requise pour cette action.',
                status: 401,
            );
        }

        return redirect()->route('admin.mfa-challenge');
    }
}
