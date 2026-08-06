<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Application\Services;

use App\Shared\Http\AppException;

final class StudioCreationRestrictedException extends AppException
{
    public function __construct(string $organizationId)
    {
        parent::__construct(
            'ADVERTISER_CREATION_RESTRICTED',
            "L'espace annonceur {$organizationId} ne peut pas créer de nouvelle ressource actuellement.",
            ['organization_id' => $organizationId],
            403,
        );
    }
}
