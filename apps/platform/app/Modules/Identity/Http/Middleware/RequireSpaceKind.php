<?php

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireSpaceKind
{
    public function handle(Request $request, Closure $next, string $kind): Response
    {
        $space = $request->attributes->get('active_space');

        abort_unless($space instanceof UserSpace && $space->kind->value === $kind, 403, 'Contexte d’espace incorrect.');

        return $next($request);
    }
}
