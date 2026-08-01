<?php

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Application\Services\AccountSessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureAccountSessionActive
{
    public function __construct(private AccountSessionService $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        $identitySession = $this->sessions->current($request);

        if ($identitySession === null || $identitySession->account_id !== $request->user()->getAuthIdentifier()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'Session révoquée ou inconnue.'], 401)
                : redirect()->route('login')->withErrors(['identifier' => 'Votre session a expiré.']);
        }

        $identitySession->forceFill(['last_seen_at' => now()])->save();
        $identitySession->device?->forceFill(['last_seen_at' => now()])->save();

        $request->attributes->set('identity_session', $identitySession);
        $request->attributes->set('active_space', $identitySession->activeSpace);

        return $next($request);
    }
}
