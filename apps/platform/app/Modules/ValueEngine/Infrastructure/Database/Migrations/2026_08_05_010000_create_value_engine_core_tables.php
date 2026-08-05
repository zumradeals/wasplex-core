<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('value_event_definitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code', 96)->unique();
            $table->string('source_module', 64);
            $table->string('status', 24)->default('active')->index();
            $table->boolean('requires_reservation')->default(true);
            $table->string('proof_type', 64)->nullable();
            $table->unsignedInteger('schema_version')->default(1);
            $table->json('metadata')->nullable();
            $table->timestampsTz();
        });

        Schema::create('value_rule_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('value_event_definition_id')
                ->constrained('value_event_definitions')
                ->restrictOnDelete();
            $table->string('code', 128);
            $table->unsignedInteger('version');
            $table->string('status', 24)->default('draft')->index();
            $table->char('country_code', 2)->nullable()->index();
            $table->char('currency', 3)->default('XOF');
            $table->string('economic_class', 32)->nullable()->index();
            $table->unsignedInteger('user_share_basis_points');
            $table->unsignedInteger('wasplex_share_basis_points');
            $table->unsignedBigInteger('minimum_event_amount_minor')->default(2);
            $table->unsignedBigInteger('maximum_event_amount_minor')->nullable();
            $table->unsignedInteger('required_attention_ms')->default(10000);
            $table->unsignedInteger('heartbeat_interval_ms')->default(5000);
            $table->unsignedInteger('attempt_ttl_seconds')->default(300);
            $table->timestampTz('effective_from')->nullable()->index();
            $table->timestampTz('effective_until')->nullable()->index();
            $table->json('calculation_parameters')->nullable();
            $table->foreignUlid('published_by_account_id')
                ->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            $table->foreignUlid('approved_by_account_id')
                ->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
            $table->unique(['code', 'version'], 'value_rule_code_version_unique');
            $table->index(
                [
                    'value_event_definition_id',
                    'status',
                    'country_code',
                    'currency',
                    'economic_class',
                ],
                'value_rule_resolution_index',
            );
        });

        Schema::create('value_campaign_budget_counters', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_funding_id')
                ->unique()
                ->constrained('campaign_fundings')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('limit_amount_minor');
            $table->unsignedBigInteger('reserved_amount_minor')->default(0);
            $table->unsignedBigInteger('settled_amount_minor')->default(0);
            $table->unsignedBigInteger('released_amount_minor')->default(0);
            $table->unsignedBigInteger('version')->default(0);
            $table->timestampsTz();
        });

        Schema::create('value_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('value_event_definition_id')
                ->constrained('value_event_definitions')
                ->restrictOnDelete();
            $table->foreignUlid('value_rule_version_id')
                ->constrained('value_rule_versions')
                ->restrictOnDelete();
            $table->foreignUlid('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignUlid('campaign_id')->constrained('campaigns')->restrictOnDelete();
            $table->foreignUlid('campaign_version_id')
                ->constrained('campaign_versions')
                ->restrictOnDelete();
            $table->foreignUlid('advertising_match_id')
                ->constrained('advertising_matches')
                ->restrictOnDelete();
            $table->foreignUlid('user_wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignUlid('advertiser_wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignUlid('campaign_funding_id')
                ->constrained('campaign_fundings')
                ->restrictOnDelete();
            $table->string('source_event_id', 160);
            $table->string('idempotency_key', 160)->unique();
            $table->string('status', 32)->default('reserved')->index();
            $table->string('economic_class', 32);
            $table->char('country_code', 2);
            $table->string('unit', 16)->default('WP');
            $table->char('currency', 3)->default('XOF');
            $table->unsignedBigInteger('gross_amount_minor');
            $table->unsignedBigInteger('user_amount_minor');
            $table->unsignedBigInteger('wasplex_amount_minor');
            $table->char('calculation_hash', 64);
            $table->timestampTz('reserved_at');
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('proof_validated_at')->nullable();
            $table->timestampTz('settled_at')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->string('release_reason', 160)->nullable();
            $table->foreignUlid('ledger_transaction_id')
                ->nullable()
                ->constrained('ledger_transactions')
                ->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(
                ['value_event_definition_id', 'source_event_id'],
                'value_attempt_source_event_unique',
            );
            $table->index(
                ['campaign_funding_id', 'status', 'expires_at'],
                'value_attempt_campaign_status_index',
            );
        });

        Schema::create('value_attention_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('value_attempt_id')
                ->unique()
                ->constrained('value_attempts')
                ->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('device_session_id', 160);
            $table->string('status', 32)->default('created')->index();
            $table->unsignedInteger('required_duration_ms');
            $table->unsignedBigInteger('visible_duration_ms')->default(0);
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->char('token_hash', 64);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('last_heartbeat_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampsTz();
            $table->index(
                ['account_id', 'device_session_id', 'status'],
                'value_attention_device_status_index',
            );
        });

        Schema::create('value_attention_heartbeats', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('value_attention_session_id')
                ->constrained('value_attention_sessions')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('sequence');
            $table->string('idempotency_key', 160)->unique();
            $table->boolean('visible');
            $table->unsignedInteger('active_duration_ms');
            $table->timestampTz('client_occurred_at');
            $table->timestampTz('received_at');
            $table->char('payload_hash', 64);
            $table->json('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(
                ['value_attention_session_id', 'sequence'],
                'value_attention_heartbeat_sequence_unique',
            );
        });

        Schema::create('value_outbox_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('aggregate_type', 96);
            $table->ulid('aggregate_id');
            $table->string('event_code', 128);
            $table->string('idempotency_key', 160)->unique();
            $table->json('payload');
            $table->timestampTz('occurred_at');
            $table->timestampTz('published_at')->nullable()->index();
            $table->unsignedInteger('publication_attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestampsTz();
            $table->index(
                ['published_at', 'occurred_at'],
                'value_outbox_unpublished_index',
            );
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE value_event_definitions ADD CONSTRAINT value_event_status_allowed CHECK (status IN ('draft','active','suspended','retired'))");
            DB::statement("ALTER TABLE value_rule_versions ADD CONSTRAINT value_rule_status_allowed CHECK (status IN ('draft','simulated','approved','scheduled','active','superseded','suspended','retired'))");
            DB::statement('ALTER TABLE value_rule_versions ADD CONSTRAINT value_rule_share_total CHECK (user_share_basis_points + wasplex_share_basis_points = 10000)');
            DB::statement('ALTER TABLE value_rule_versions ADD CONSTRAINT value_rule_share_range CHECK (user_share_basis_points <= 10000 AND wasplex_share_basis_points <= 10000)');
            DB::statement("ALTER TABLE value_attempts ADD CONSTRAINT value_attempt_status_allowed CHECK (status IN ('reserved','attention_active','proof_pending','completed','released','expired','rejected'))");
            DB::statement('ALTER TABLE value_attempts ADD CONSTRAINT value_attempt_amount_balance CHECK (gross_amount_minor = user_amount_minor + wasplex_amount_minor)');
            DB::statement("ALTER TABLE value_attention_sessions ADD CONSTRAINT value_attention_status_allowed CHECK (status IN ('created','active','paused','backgrounded','completed','abandoned','expired','rejected'))");
            DB::statement('ALTER TABLE value_campaign_budget_counters ADD CONSTRAINT value_campaign_budget_non_negative CHECK (reserved_amount_minor + settled_amount_minor <= limit_amount_minor)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('value_outbox_events');
        Schema::dropIfExists('value_attention_heartbeats');
        Schema::dropIfExists('value_attention_sessions');
        Schema::dropIfExists('value_attempts');
        Schema::dropIfExists('value_campaign_budget_counters');
        Schema::dropIfExists('value_rule_versions');
        Schema::dropIfExists('value_event_definitions');
    }
};
