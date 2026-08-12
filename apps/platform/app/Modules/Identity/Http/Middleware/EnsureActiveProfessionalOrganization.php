<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Application\Services\SpaceService;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\ProfessionalSpace;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use App\Shared\Http\ApiErrorResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureActiveProfessionalOrganization
{
    public function __construct(private readonly SpaceService $spaces) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Account $account */
        $account = $request->user();
        $activeSpace = $this->spaces->activeSpace($account, $request);

        if ($activeSpace === null || $activeSpace->space_type !== UserSpace::TYPE_PROFESSIONAL || $activeSpace->organization_id === null) {
            return ApiErrorResponse::make(
                $request,
                'PROFESSIONAL_SPACE_REQUIRED',
                "L'espace professionnel actif est requis pour cette action.",
                status: 403,
            );
        }

        $profile = ProfessionalSpace::query()
            ->where('user_space_id', $activeSpace->id)
            ->where('verification_status', ProfessionalSpace::VERIFICATION_VERIFIED)
            ->first();

        if ($profile === null) {
            return ApiErrorResponse::make(
                $request,
                'PROFESSIONAL_SPACE_NOT_VERIFIED',
                "L'espace professionnel doit être vérifié avant cette action.",
                status: 403,
            );
        }

        $request->attributes->set('professional_organization_id', $activeSpace->organization_id);
        $request->attributes->set('professional_space_id', $profile->id);

        return $next($request);
    }
}
