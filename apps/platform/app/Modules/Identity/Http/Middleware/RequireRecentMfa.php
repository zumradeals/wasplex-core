<?php

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Application\Services\AccessAuditor;
use App\Modules\Identity\Infrastructure\Models\AccountSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireRecentMfa
{
    public function __construct(private AccessAuditor $auditor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $identitySession = $request->attributes->get('identity_session');
        $verifiedAt = $identitySession instanceof AccountSession ? $identitySession->mfa_verified_at : null;

        if ($verifiedAt === null || $verifiedAt->lt(now()->subMinutes(30))) {
            $this->auditor->record($request, 'mfa.checked', 'denied');

            return $request->expectsJson()
                ? response()->json(['message' => 'MFA récente requise.', 'code' => 'mfa_required'], 423)
                : redirect()->route('security.mfa.challenge');
        }

        $this->auditor->record($request, 'mfa.checked', 'granted');

        return $next($request);
    }
}
