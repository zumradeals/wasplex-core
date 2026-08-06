<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Application\Services;

use RuntimeException;

final class ProfileTaxonomyNotActiveException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct("Cette information de profil n'est pas activée par l'administration.");
    }
}
