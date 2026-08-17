<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Application\Services\PublicCampaignService;
use App\Modules\Identity\Application\Services\SpaceService;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class GuestPagesController extends Controller
{
    public function __construct(
        private readonly SpaceService $spaces,
        private readonly PublicCampaignService $publicCampaigns,
    ) {}

    /**
     * Un visiteur découvre Wasplex directement par son Feed public. Un compte
     * déjà connecté retrouve son espace actif sans repasser par l'acquisition.
     */
    public function landing(Request $request): Response|RedirectResponse
    {
        /** @var Account|null $account */
        $account = $request->user();

        if ($account !== null) {
            $active = $this->spaces->activeSpace($account, $request);

            return redirect(match ($active?->space_type) {
                'advertiser' => '/studio',
                'admin' => '/admin',
                default => '/app',
            });
        }

        return Inertia::render('Identity/GuestFeed', [
            'campaigns' => $this->publicCampaigns->feed(),
            'simulatedRewardMinor' => max(0, (int) config('feed.guest_simulated_reward_minor', 30)),
        ]);
    }

    public function login(): Response
    {
        return Inertia::render('Identity/Landing', [
            'initialMode' => 'login',
        ]);
    }

    public function register(): Response
    {
        return Inertia::render('Identity/Landing', [
            'initialMode' => 'register',
        ]);
    }
}
