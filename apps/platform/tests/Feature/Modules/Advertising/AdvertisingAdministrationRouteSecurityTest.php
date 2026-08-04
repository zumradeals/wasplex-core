<?php

namespace Tests\Feature\Modules\Advertising;

use Illuminate\Routing\Route;
use Tests\TestCase;

final class AdvertisingAdministrationRouteSecurityTest extends TestCase
{
    public function test_administration_routes_require_admin_space_recent_mfa_and_explicit_capabilities(): void
    {
        $routes = app('router')->getRoutes();
        $index = $routes->getByName('advertising.admin.index');
        $publish = $routes->getByName('advertising.admin.configuration.publish');
        $purpose = $routes->getByName('advertising.admin.purposes.status');
        $taxonomy = $routes->getByName('advertising.admin.taxonomies.status');

        expect($index)->toBeInstanceOf(Route::class)
            ->and($publish)->toBeInstanceOf(Route::class)
            ->and($purpose)->toBeInstanceOf(Route::class)
            ->and($taxonomy)->toBeInstanceOf(Route::class);

        $shared = [
            'web',
            'auth',
            'identity.session',
            'space.kind:administration',
            'mfa.recent',
        ];

        foreach ([$index, $publish, $purpose, $taxonomy] as $route) {
            foreach ($shared as $middleware) {
                expect($route->gatherMiddleware())->toContain($middleware);
            }
        }

        expect($index->gatherMiddleware())
            ->toContain('capability:advertising.configuration.view')
            ->toContain('capability:advertising.match.audit.view')
            ->and($publish->gatherMiddleware())
            ->toContain('capability:advertising.configuration.manage')
            ->toContain('capability:advertising.configuration.publish')
            ->and($purpose->gatherMiddleware())
            ->toContain('capability:advertising.configuration.manage')
            ->and($taxonomy->gatherMiddleware())
            ->toContain('capability:advertising.configuration.manage');
    }
}
