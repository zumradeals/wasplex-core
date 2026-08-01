<?php

namespace App\Modules\Identity\Domain\Enums;

enum IdentifierType: string
{
    case Email = 'email';
    case Phone = 'phone';
    case Username = 'username';
}
