<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Application\Services\AuditLogger;
use App\Modules\Identity\Application\Services\CapabilityChecker;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Shared\Http\ApiErrorResponse;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: ensure.capability:<code>[,<scope_type>:<route_param>]
 * Docs/17 #21: acteur + espace + organisation + capacité + périmètre.
 */
final class EnsureCapability
{
    public function __construct(
        private readonly CapabilityChecker $capabilities,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(Request $request, Closure $next, string $capabilityCode, ?string $scopeSpec = null): Response
    {
        /** @var Account|null $account */
        $account = $request->user();

        if ($account === null) {
            return ApiErrorResponse::make($request, 'UNAUTHENTICATED', 'Authentification requise.', status: 401);
        }

        [$scopeType, $scopeId] = $this->resolveScope($request, $scopeSpec);

        if (! $this->capabilities->accountHas($account, $capabilityCode, $scopeType, $scopeId)) {
            $this->auditLogger->record('DataAccessDenied', [
                'actor_account_id' => $account->id,
                'organization_id' => $scopeType === 'organization' ? $scopeId : null,
                'capability_code' => $capabilityCode,
            ], $request);

            return ApiErrorResponse::make(
                $request,
                'CAPABILITY_DENIED',
                "La capacité « {$capabilityCode} » est requise.",
                ['capability' => $capabilityCode],
                403,
            );
        }

        return $next($request);
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveScope(Request $request, ?string $scopeSpec): array
    {
        if ($scopeSpec === null) {
            return [null, null];
        }

        [$scopeType, $routeParam] = array_pad(explode(':', $scopeSpec, 2), 2, null);

        // Falls back to a request attribute of the same name when there is
        // no literal route parameter — e.g. an organization_id resolved
        // from the account's active space by an earlier middleware
        // (App\Modules\Identity\Http\Middleware\
        // EnsureActiveAdvertiserOrganization) rather than from the URL.
        $parameter = $routeParam !== null
            ? ($request->route($routeParam) ?? $request->attributes->get($routeParam))
            : null;
        $scopeId = $parameter instanceof Model
            ? (string) $parameter->getKey()
            : (is_string($parameter) ? $parameter : null);

        return [$scopeType, $scopeId];
    }
}
