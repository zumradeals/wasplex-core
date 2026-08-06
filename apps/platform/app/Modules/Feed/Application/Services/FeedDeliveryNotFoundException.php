<?php

declare(strict_types=1);

namespace App\Modules\Feed\Application\Services;

use RuntimeException;

final class FeedDeliveryNotFoundException extends RuntimeException
{
    public function __construct(string $deliveryId)
    {
        parent::__construct("Livraison introuvable : {$deliveryId}.");
    }
}
