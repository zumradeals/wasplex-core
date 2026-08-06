<?php

declare(strict_types=1);

use App\Modules\Subscriptions\Http\Controllers\Webhook\SubscriptionPaymentWebhookController;
use Illuminate\Support\Facades\Route;

// Deliberately outside the "web" group: server-to-server, signed,
// no session/CSRF (same discipline as AdvertiserWallet's webhook, P003).
Route::post('/webhooks/geniuspay-subscriptions', SubscriptionPaymentWebhookController::class);
