<?php

declare(strict_types=1);

namespace App\Shared\Payments;

use Illuminate\Support\ServiceProvider;

/**
 * Binds the shared payment provider contract once, so every module that
 * needs to charge/reconcile through GeniusPay (AdvertiserWallet,
 * Subscriptions, …) resolves the same adapter without re-declaring the
 * binding itself.
 */
final class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentProviderContract::class, GeniusPayAdapter::class);
    }
}
