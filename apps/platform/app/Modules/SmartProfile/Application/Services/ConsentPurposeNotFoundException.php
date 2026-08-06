<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Application\Services;

use RuntimeException;

final class ConsentPurposeNotFoundException extends RuntimeException
{
    public function __construct(string $purposeCode)
    {
        parent::__construct("Finalité de consentement introuvable : {$purposeCode}.");
    }
}
