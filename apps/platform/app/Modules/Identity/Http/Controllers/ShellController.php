<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Campaign\Application\Services\CampaignReviewService;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use App\Modules\Wallet\Application\Services\WalletCatalog;
use App\Modules\Wallet\Application\Services\WalletPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ShellController
{
    public function __construct(
        private readonly WalletCatalog $wallets,
        private readonly WalletPresenter $walletPresenter,
        private readonly CampaignReviewService $campaignReviews,
    ) {}

    public function home(Request $request): RedirectResponse
    {
        $space = $request->attributes->get('active_space');

        return match ($space?->kind) {
            SpaceKind::Advertiser => redirect()->route('studio.dashboard'),
            SpaceKind::Administration => redirect()->route('admin.dashboard'),
            default => redirect()->route('user.dashboard'),
        };
    }

    public function user(Request $request): Response
    {
        return Inertia::render('Spaces/UserDashboard', $this->props($request));
    }

    public function advertiser(Request $request): Response
    {
        return Inertia::render('Spaces/AdvertiserDashboard', $this->props($request));
    }

    public function admin(Request $request): Response
    {
        return Inertia::render('Spaces/AdminDashboard', [
            ...$this->props($request),
            'metrics' => [
                'accounts' => Account::query()->count(),
                'spaces' => UserSpace::query()->count(),
                'campaignReviews' => $this->campaignReviews->pendingCount(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function props(Request $request): array
    {
        /** @var Account $account */
        $account = $request->user();
        $account->load(['profile', 'spaces.organization']);
        $activeSpace = $request->attributes->get('active_space');

        $props = [
            'account' => [
                'id' => $account->id,
                'displayName' => $account->profile?->display_name,
            ],
            'activeSpace' => [
                'id' => $activeSpace?->id,
                'kind' => $activeSpace?->kind->value,
                'label' => $activeSpace?->label,
            ],
            'spaces' => $account->spaces->map(fn (UserSpace $space): array => [
                'id' => $space->id,
                'kind' => $space->kind->value,
                'label' => $space->label,
                'active' => $activeSpace?->id === $space->id,
            ])->values(),
        ];

        if ($activeSpace instanceof UserSpace && in_array($activeSpace->kind, [SpaceKind::User, SpaceKind::Advertiser], true)) {
            $props['wallet'] = $this->walletPresenter->wallet($this->wallets->forSpace($activeSpace));
        }

        return $props;
    }
}
