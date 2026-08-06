<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use RuntimeException;

final class NoPublishedPriceCatalogException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct("Aucun catalogue de prix publié — un administrateur doit d'abord publier une version tarifée.");
    }
}
