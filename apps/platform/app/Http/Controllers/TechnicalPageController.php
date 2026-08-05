<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * P000 verification page: proves the socle (Laravel, Inertia, Vue,
 * PostgreSQL, Redis) is wired end to end without any business rule.
 */
final class TechnicalPageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Technical', [
            'traceId' => $request->attributes->get('trace_id'),
            'environment' => app()->environment(),
            'versions' => [
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
            ],
            'checks' => [
                'database' => $this->probe(fn () => DB::select('select 1') !== null),
                'redis' => $this->probe(fn () => Redis::ping() !== false),
            ],
        ]);
    }

    /**
     * @param  callable(): bool  $probe
     */
    private function probe(callable $probe): bool
    {
        try {
            return $probe();
        } catch (Throwable) {
            return false;
        }
    }
}
