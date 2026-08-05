<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\Services\SpaceService;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MeSpacesController extends Controller
{
    public function __construct(private readonly SpaceService $spaces) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        $memberships = $this->spaces->accessibleMemberships($account);
        $active = $this->spaces->activeSpace($account, $request);

        return new JsonResponse([
            'active_space_id' => $active?->id,
            'spaces' => $memberships->map(fn ($m) => [
                'user_space_id' => $m->space->id,
                'space_type' => $m->space->space_type,
                'organization_id' => $m->space->organization_id,
                'organization_name' => $m->space->organization?->name,
                'is_default' => $m->is_default,
            ])->values(),
        ]);
    }

    public function switch(Request $request, string $userSpace): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        $space = $this->spaces->switchTo($account, $userSpace, $request);

        return new JsonResponse([
            'active_space_id' => $space->id,
            'space_type' => $space->space_type,
        ]);
    }
}
