<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\Services\SpaceService;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class GuestPagesController extends Controller
{
    public function __construct(private readonly SpaceService $spaces) {}

    /**
     * Docs produit : un compte déjà connecté qui revisite la racine publique
     * retrouve directement son espace actif, il ne revoit pas la vitrine.
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

        return Inertia::render('Identity/Landing');
    }

    public function login(): Response
    {
        return Inertia::render('Identity/Login');
    }

    public function register(): Response
    {
        return Inertia::render('Identity/Register');
    }
}
