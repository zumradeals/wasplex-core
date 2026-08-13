<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Application\Services;

use App\Modules\Wallet\Infrastructure\Models\UserWalletDeposit;
use App\Shared\Http\AppException;
use App\Shared\Payments\PaymentProviderContract;
use Illuminate\Support\Facades\Log;

final class UserWalletGeniusPayWebhookHandler
{
    public function __construct(
        private readonly PaymentProviderContract $provider,
        private readonly UserWalletDepositService $deposits,
    ) {}

    public function handle(string $rawPayload, array $headers): void
    {
        if (! $this->provider->verifyWebhookSignature($rawPayload, $headers)) {
            throw new AppException('WEBHOOK_SIGNATURE_INVALID', 'Signature de webhook invalide.', [], 401);
        }

        $event = $this->provider->parseWebhookPayload($rawPayload);
        $deposit = $event->providerReference !== null
            ? UserWalletDeposit::query()->where('provider_reference', $event->providerReference)->first()
            : null;

        if ($deposit === null) {
            Log::channel('structured')->info('wallet.deposit.webhook.unknown_reference', [
                'provider_reference' => $event->providerReference,
            ]);

            return;
        }

        if ($deposit->isTerminal()) {
            Log::channel('structured')->info('wallet.deposit.webhook.duplicated', ['deposit_id' => $deposit->id]);

            return;
        }

        $this->deposits->reconcile($deposit->id, $deposit->account_id);
    }
}
