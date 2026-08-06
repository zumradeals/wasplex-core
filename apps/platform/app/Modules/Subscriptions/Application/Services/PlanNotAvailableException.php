<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Services;

use App\Shared\Http\AppException;

final class PlanNotAvailableException extends AppException
{
    public function __construct(string $planVersionId)
    {
        parent::__construct('PLAN_NOT_AVAILABLE', "Le plan « {$planVersionId} » n'est pas disponible à la souscription.", ['plan_version_id' => $planVersionId], 422);
    }
}
