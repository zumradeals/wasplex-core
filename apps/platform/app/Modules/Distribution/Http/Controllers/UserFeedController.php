<?php

namespace App\Modules\Distribution\Http\Controllers;

use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use App\Modules\Wallet\Application\Services\WalletCatalog;
use App\Modules\Wallet\Application\Services\WalletPresenter;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserFeedController extends Controller
{
    public function __construct(
        private WalletCatalog $wallets,
        private WalletPresenter $walletPresenter,
    ) {}

    public function __invoke(Request $request): Response
    {
        $account = $request->user();
        $activeSpace = $request->attributes->get('active_space');

        if (! $account instanceof Account) {
            abort(401);
        }

        if (! $activeSpace instanceof UserSpace || $activeSpace->kind !== SpaceKind::User) {
            abort(403);
        }

        $account->load(['profile', 'spaces.organization']);

        return Inertia::render('User/Feed', [
            'account' => [
                'id' => $account->id,
                'displayName' => $account->profile?->display_name,
            ],
            'activeSpace' => [
                'id' => $activeSpace->id,
                'kind' => $activeSpace->kind->value,
                'label' => $activeSpace->label,
            ],
            'spaces' => $account->spaces->map(fn (UserSpace $space): array => [
                'id' => $space->id,
                'kind' => $space->kind->value,
                'label' => $space->label,
                'active' => $activeSpace->id === $space->id,
            ])->values(),
            'wallet' => $this->walletPresenter->wallet($this->wallets->forSpace($activeSpace)),
            'feedConfig' => [
                'defaultMarket' => strtoupper((string) config('distribution.default_market', 'CI')),
                'mediaLoadTimeoutMs' => max(
                    3000,
                    min(60000, (int) config('distribution.media_load_timeout_ms', 15000)),
                ),
                'sessionStorageKey' => 'wasplex.feed.session.'.$account->id,
                'dataSaverStorageKey' => 'wasplex.feed.data-saver',
            ],
            'endpoints' => [
                'sessions' => route('distribution.feed.sessions.store', absolute: false),
                'prepare' => route(
                    'distribution.feed.sessions.prepare',
                    ['session' => '__SESSION__'],
                    false,
                ),
                'confirm' => route(
                    'distribution.feed.deliveries.confirm',
                    ['delivery' => '__DELIVERY__'],
                    false,
                ),
                'release' => route(
                    'distribution.feed.deliveries.release',
                    ['delivery' => '__DELIVERY__'],
                    false,
                ),
                'dismiss' => route(
                    'distribution.feed.deliveries.dismiss',
                    ['delivery' => '__DELIVERY__'],
                    false,
                ),
            ],
        ]);
    }
}
