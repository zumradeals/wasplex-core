<?php

declare(strict_types=1);

use App\Modules\AdvertiserWallet\Http\Controllers\Webhook\GeniusPayWebhookController;
use Illuminate\Support\Facades\Route;

/**
 * Server-to-server, signed, stateless — deliberately outside the "web"
 * middleware group (no session, no CSRF token to present).
 */
Route::post('/webhooks/geniuspay', GeniusPayWebhookController::class);
