<?php

namespace App\Modules\Wallet\Application\Contracts;

use App\Modules\Wallet\Application\Data\CreateGatewayPayment;
use App\Modules\Wallet\Application\Data\GatewayPayment;
use App\Modules\Wallet\Application\Data\GatewayWebhook;

interface PaymentGatewayContract
{
    public function createPayment(CreateGatewayPayment $request): GatewayPayment;

    public function fetchPayment(string $reference): GatewayPayment;

    /** @param array<string, string|null> $headers */
    public function parseWebhook(string $payload, array $headers): GatewayWebhook;
}
