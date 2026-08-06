<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Http\Middleware;

use App\Modules\Identity\Application\Services\SpaceService;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use App\Shared\Http\ApiErrorResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The advertiser Wallet is scoped to the account's *active* space (docs/09
 * #6: switching space recalculates the permission context). Resolves it via
 * Identity's SpaceService (a deliberately cross-module-reusable
 * application service, like EnsureCapability/EnsureRecentMfa) and stashes
 * the organization_id so controllers never have to re-derive it.
 */
final class EnsureActiveAdvertiserOrganization
{
    public function __construct(private readonly SpaceService $spaces) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Account $account */
        $account = $request->user();

        $activeSpace = $this->spaces->activeSpace($account, $request);

        if ($activeSpace === null || $activeSpace->space_type !== UserSpace::TYPE_ADVERTISER || $activeSpace->organization_id === null) {
            return ApiErrorResponse::make(
                $request,
                'ADVERTISER_SPACE_REQUIRED',
                "L'espace annonceur actif est requis pour cette action.",
                status: 403,
            );
        }

        $request->attributes->set('advertiser_organization_id', $activeSpace->organization_id);

        return $next($request);
    }
}
