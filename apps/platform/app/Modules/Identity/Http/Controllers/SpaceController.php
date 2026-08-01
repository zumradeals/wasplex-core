<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Services\AccessAuditor;
use App\Modules\Identity\Application\Services\SpaceService;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\AccountSession;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class SpaceController
{
    public function __construct(
        private SpaceService $spaces,
        private AccessAuditor $auditor,
    ) {}

    public function activateAdvertiser(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'organization_name' => ['required', 'string', 'min:2', 'max:160'],
        ]);

        /** @var Account $account */
        $account = $request->user();
        $space = $this->spaces->activateAdvertiser($account, $validated['organization_name']);
        $this->auditor->record($request, 'advertiser_space.activated', 'success', context: ['space_id' => $space->id]);

        return $request->expectsJson()
            ? response()->json(['data' => ['space_id' => $space->id]], 201)
            : redirect()->route('space.home');
    }

    public function switch(Request $request, UserSpace $space): JsonResponse|RedirectResponse
    {
        /** @var Account $account */
        $account = $request->user();
        /** @var AccountSession $session */
        $session = $request->attributes->get('identity_session');

        $this->spaces->switch($account, $session, $space);
        $request->attributes->set('active_space', $space);
        $this->auditor->record($request, 'space.switched', 'success', context: ['space_id' => $space->id]);

        return $request->expectsJson()
            ? response()->json(['data' => ['active_space_id' => $space->id]])
            : redirect()->route('space.home');
    }
}
