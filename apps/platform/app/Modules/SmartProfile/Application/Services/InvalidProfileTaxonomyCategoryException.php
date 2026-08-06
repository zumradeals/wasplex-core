<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Application\Services;

use RuntimeException;

final class InvalidProfileTaxonomyCategoryException extends RuntimeException
{
    public function __construct(string $category)
    {
        parent::__construct("Catégorie inconnue : {$category}.");
    }
}
