<?php

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Application\Services\AccessAuditor;
use App\Modules\Identity\Application\Services\CapabilityService;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireCapability
{
    public function __construct(
        private CapabilityService $capabilities,
        private AccessAuditor $auditor,
    ) {}

    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $account = $request->user();
        $space = $request->attributes->get('active_space');

        if (! $account instanceof Account || ! $this->capabilities->allows(
            $account,
            $capability,
            $space instanceof UserSpace ? $space : null,
        )) {
            $this->auditor->record($request, 'capability.checked', 'denied', $capability);

            abort(403, 'Capacité requise non accordée.');
        }

        $this->auditor->record($request, 'capability.checked', 'granted', $capability);

        return $next($request);
    }
}
