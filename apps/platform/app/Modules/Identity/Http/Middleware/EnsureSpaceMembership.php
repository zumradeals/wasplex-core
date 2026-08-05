<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Application\Services\SpaceService;
use App\Modules\Identity\Infrastructure\Models\Account;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web shell gate: the account must hold at least one active membership of
 * the given space type (docs/09 #5-#6). Redirects back to the personal
 * shell rather than exposing a raw error, since this protects a page, not
 * an API resource.
 */
final class EnsureSpaceMembership
{
    public function __construct(private readonly SpaceService $spaces) {}

    public function handle(Request $request, Closure $next, string $spaceType): Response
    {
        /** @var Account $account */
        $account = $request->user();

        $hasSpace = $this->spaces->accessibleMemberships($account)
            ->contains(fn ($m) => $m->space->space_type === $spaceType);

        if (! $hasSpace) {
            return redirect()->route('app.home')->with('error', "Aucun accès à l'espace {$spaceType}.");
        }

        return $next($request);
    }
}
