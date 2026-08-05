<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Identity\Application\Services\SpaceService;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var Account|null $account */
        $account = $request->user();

        return [
            ...parent::share($request),
            'auth' => $account === null ? null : [
                'account' => [
                    'id' => $account->id,
                    'status' => $account->status,
                    'mfa_enabled' => $account->totp_enabled_at !== null,
                ],
                'spaces' => app(SpaceService::class)->accessibleMemberships($account)->map(fn ($m) => [
                    'user_space_id' => $m->space->id,
                    'space_type' => $m->space->space_type,
                    'organization_id' => $m->space->organization_id,
                    'organization_name' => $m->space->organization?->name,
                ])->values(),
                'active_space_id' => app(SpaceService::class)->activeSpace($account, $request)?->id,
            ],
        ];
    }
}
