<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only (docs/03 §26: "historique immuable") — no updated_at,
        // no update/delete route ever touches this table.
        Schema::create('subscription_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_subscription_id')->constrained('user_subscriptions')->cascadeOnDelete();

            // docs/03 §23: SubscriptionSelected/PaymentReceived/Activated/
            // Renewed/Upgraded/DowngradeScheduled/Expired/Suspended/
            // Refunded, AdQuotaConsumed/Restored/Reset.
            $table->string('event_type');
            $table->json('payload')->nullable();

            // Nullable: most events are one-off writes with nothing to
            // deduplicate. AdQuotaConsumed/Restored set this from the
            // caller's own idempotency key (P008/P009 will own generating
            // it) so a retried delivery never double-consumes quota.
            $table->string('idempotency_key')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_subscription_id', 'created_at']);
            $table->unique(['user_subscription_id', 'event_type', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
    }
};
