<?php

declare(strict_types=1);

namespace App\Modules\Matching\Http\Controllers\User;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Matching\Application\Services\EligibleCampaignsForAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * « Campagnes qui vous correspondent » — Mon Espace (docs/chantiers/
 * P008-CHANTIER.md §2.4). Self-service, aucune capacité requise.
 */
final class EligibleCampaignsController extends Controller
{
    public function __construct(private readonly EligibleCampaignsForAccountService $eligibleCampaigns) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        return response()->json(['campaigns' => $this->eligibleCampaigns->forAccount($account->id)]);
    }
}
