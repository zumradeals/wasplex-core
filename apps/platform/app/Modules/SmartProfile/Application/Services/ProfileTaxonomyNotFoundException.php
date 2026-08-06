<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Application\Services;

use RuntimeException;

final class ProfileTaxonomyNotFoundException extends RuntimeException
{
    public function __construct(string $taxonomyId)
    {
        parent::__construct("Information de profil introuvable : {$taxonomyId}.");
    }
}
