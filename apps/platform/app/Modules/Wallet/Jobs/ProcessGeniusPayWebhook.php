<?php

namespace App\Modules\Wallet\Jobs;

use App\Modules\Wallet\Application\Contracts\PaymentGatewayContract;
use App\Modules\Wallet\Application\Services\WalletDepositService;
use App\Modules\Wallet\Domain\Enums\ProviderEventStatus;
use App\Modules\Wallet\Infrastructure\Models\WalletProviderEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ProcessGeniusPayWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(public readonly string $providerEventId)
    {
        $this->onQueue('payments');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 120, 300];
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("geniuspay-event:{$this->providerEventId}"))
                ->releaseAfter(10)
                ->expireAfter(90),
        ];
    }

    public function handle(PaymentGatewayContract $gateway, WalletDepositService $deposits): void
    {
        $event = DB::transaction(function (): ?WalletProviderEvent {
            $event = WalletProviderEvent::query()->lockForUpdate()->find($this->providerEventId);
            if ($event === null || in_array($event->status, [ProviderEventStatus::Processed, ProviderEventStatus::Ignored], true)) {
                return null;
            }

            $event->forceFill([
                'status' => ProviderEventStatus::Processing,
                'attempts' => $event->attempts + 1,
                'last_error' => null,
            ])->save();

            return $event;
        });

        if ($event === null || $event->payment_reference === null) {
            return;
        }

        try {
            $payment = $gateway->fetchPayment($event->payment_reference);
            $deposits->synchronizePayment($payment);

            WalletProviderEvent::query()->whereKey($event->id)->update([
                'status' => ProviderEventStatus::Processed->value,
                'processed_at' => now(),
                'last_error' => null,
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            WalletProviderEvent::query()->whereKey($event->id)->update([
                'status' => ProviderEventStatus::Failed->value,
                'last_error' => $exception::class,
                'updated_at' => now(),
            ]);

            throw $exception;
        }
    }
}
