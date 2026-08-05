<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising_feed_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUuid('user_subscription_id')->constrained('user_subscriptions')->restrictOnDelete();
            $table->foreignUuid('subscription_quota_counter_id')->constrained('subscription_quota_counters')->restrictOnDelete();
            $table->foreignUuid('economic_class_id')->constrained('economic_classes')->restrictOnDelete();
            $table->foreignUuid('economic_class_version_id')->constrained('economic_class_versions')->restrictOnDelete();
            $table->string('idempotency_key', 64);
            $table->string('status', 24)->default('active')->index();
            $table->char('market', 2);
            $table->char('currency', 3)->default('XOF');
            $table->string('territory', 120)->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['account_id', 'idempotency_key'], 'advertising_feed_session_idempotency_unique');
        });

        Schema::create('advertising_delivery_leases', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('feed_session_id')->constrained('advertising_feed_sessions')->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUlid('advertising_match_id')->constrained('advertising_matches')->restrictOnDelete();
            $table->string('idempotency_key', 64);
            $table->string('status', 24)->default('reserved')->index();
            $table->timestampTz('reserved_at');
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->timestampsTz();
            $table->unique(['account_id', 'idempotency_key'], 'advertising_delivery_lease_idempotency_unique');
            $table->index(['advertising_match_id', 'status'], 'advertising_delivery_lease_match_status_index');
        });

        Schema::create('advertising_deliveries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('advertising_delivery_lease_id')->unique()->constrained('advertising_delivery_leases')->restrictOnDelete();
            $table->foreignUlid('feed_session_id')->constrained('advertising_feed_sessions')->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUlid('advertising_match_id')->constrained('advertising_matches')->restrictOnDelete();
            $table->foreignUlid('campaign_id')->constrained('campaigns')->restrictOnDelete();
            $table->foreignUlid('campaign_version_id')->constrained('campaign_versions')->restrictOnDelete();
            $table->foreignUlid('creative_asset_id')->constrained('creative_assets')->restrictOnDelete();
            $table->foreignUuid('subscription_quota_counter_id')->constrained('subscription_quota_counters')->restrictOnDelete();
            $table->foreignUuid('economic_class_version_id')->constrained('economic_class_versions')->restrictOnDelete();
            $table->string('idempotency_key', 64);
            $table->string('status', 24)->default('prepared')->index();
            $table->string('creative_type', 24);
            $table->string('media_reference', 500);
            $table->string('cta_reference', 500)->nullable();
            $table->string('explanation_reference', 500);
            $table->json('value_preview')->nullable();
            $table->string('context_hash', 64);
            $table->timestampTz('prepared_at');
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('invalidated_at')->nullable();
            $table->timestampTz('expires_at')->index();
            $table->timestampsTz();
            $table->unique(['account_id', 'idempotency_key'], 'advertising_delivery_idempotency_unique');
            $table->index(['advertising_match_id', 'status'], 'advertising_delivery_match_status_index');
        });

        Schema::create('advertising_delivery_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('advertising_delivery_id')->constrained('advertising_deliveries')->cascadeOnDelete();
            $table->string('event_type', 80)->index();
            $table->string('idempotency_key', 120)->unique();
            $table->timestampTz('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('advertising_quota_consumptions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('advertising_delivery_id')->unique()->constrained('advertising_deliveries')->restrictOnDelete();
            $table->foreignUuid('subscription_quota_counter_id')->constrained('subscription_quota_counters')->restrictOnDelete();
            $table->foreignUuid('economic_class_version_id')->constrained('economic_class_versions')->restrictOnDelete();
            $table->string('event_code', 40)->default('AD_DELIVERED');
            $table->unsignedInteger('quota_before');
            $table->unsignedInteger('quota_after');
            $table->string('idempotency_key', 120)->unique();
            $table->timestampTz('consumed_at');
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('advertising_delivery_outbox_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('aggregate_type', 80);
            $table->ulid('aggregate_id');
            $table->string('event_code', 80)->index();
            $table->string('idempotency_key', 120)->unique();
            $table->json('payload');
            $table->timestampTz('occurred_at');
            $table->timestampTz('published_at')->nullable()->index();
            $table->unsignedInteger('publish_attempts')->default(0);
            $table->timestampsTz();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE advertising_feed_sessions ADD CONSTRAINT advertising_feed_session_status_allowed CHECK (status IN ('active','expired','closed'))");
            DB::statement("ALTER TABLE advertising_delivery_leases ADD CONSTRAINT advertising_delivery_lease_status_allowed CHECK (status IN ('reserved','delivered','expired','released'))");
            DB::statement("ALTER TABLE advertising_deliveries ADD CONSTRAINT advertising_delivery_status_allowed CHECK (status IN ('prepared','delivered','invalidated','superseded'))");
            DB::statement('ALTER TABLE advertising_quota_consumptions ADD CONSTRAINT advertising_quota_consumption_order CHECK (quota_after <= quota_before)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising_delivery_outbox_events');
        Schema::dropIfExists('advertising_quota_consumptions');
        Schema::dropIfExists('advertising_delivery_events');
        Schema::dropIfExists('advertising_deliveries');
        Schema::dropIfExists('advertising_delivery_leases');
        Schema::dropIfExists('advertising_feed_sessions');
    }
};
