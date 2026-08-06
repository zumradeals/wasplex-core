<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Services;

use App\Shared\Http\AppException;

final class InvalidWebhookSignatureException extends AppException
{
    public function __construct()
    {
        parent::__construct('WEBHOOK_SIGNATURE_INVALID', 'Signature de webhook invalide.', [], 401);
    }
}
