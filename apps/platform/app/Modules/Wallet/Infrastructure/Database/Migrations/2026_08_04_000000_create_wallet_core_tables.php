<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignUlid('space_id')->constrained('user_spaces')->restrictOnDelete();
            $table->foreignUlid('organization_id')->nullable()->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('available_ledger_account_id')->nullable()->unique()->constrained('ledger_accounts')->restrictOnDelete();
            $table->foreignUlid('reserved_ledger_account_id')->nullable()->unique()->constrained('ledger_accounts')->restrictOnDelete();
            $table->foreignUlid('pending_ledger_account_id')->nullable()->unique()->constrained('ledger_accounts')->restrictOnDelete();
            $table->string('kind', 24);
            $table->string('unit', 12)->default('WP');
            $table->string('currency', 12)->default('XOF');
            $table->string('status', 24)->default('active');
            $table->timestampsTz();
            $table->unique(['space_id', 'unit', 'currency']);
            $table->index(['account_id', 'kind', 'status']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('wallet_balance_projections', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('wallet_id')->unique()->constrained('wallets')->cascadeOnDelete();
            $table->unsignedBigInteger('available_minor')->default(0);
            $table->unsignedBigInteger('reserved_minor')->default(0);
            $table->unsignedBigInteger('pending_minor')->default(0);
            $table->unsignedBigInteger('version')->default(0);
            $table->foreignUlid('last_ledger_transaction_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->timestampTz('rebuilt_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('wallet_operations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignUlid('ledger_transaction_id')->nullable()->unique()->constrained('ledger_transactions')->restrictOnDelete();
            $table->string('type', 48);
            $table->string('direction', 16);
            $table->string('status', 24);
            $table->unsignedBigInteger('amount_minor');
            $table->string('unit', 12);
            $table->string('currency', 12);
            $table->string('business_reference', 160);
            $table->string('idempotency_key', 160);
            $table->string('description', 240);
            $table->json('metadata')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampsTz();
            $table->unique(['wallet_id', 'idempotency_key']);
            $table->index(['wallet_id', 'occurred_at']);
            $table->index(['wallet_id', 'type', 'status']);
        });

        Schema::create('wallet_reservations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignUlid('reserve_ledger_transaction_id')->nullable()->unique()->constrained('ledger_transactions')->restrictOnDelete();
            $table->foreignUlid('resolution_ledger_transaction_id')->nullable()->unique()->constrained('ledger_transactions')->restrictOnDelete();
            $table->string('purpose', 80);
            $table->unsignedBigInteger('amount_minor');
            $table->string('unit', 12);
            $table->string('currency', 12);
            $table->string('status', 24);
            $table->string('business_reference', 160);
            $table->string('idempotency_key', 160);
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['wallet_id', 'business_reference']);
            $table->unique(['wallet_id', 'idempotency_key']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('wallet_deposits', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignUlid('ledger_transaction_id')->nullable()->unique()->constrained('ledger_transactions')->restrictOnDelete();
            $table->string('provider', 48)->default('geniuspay');
            $table->string('provider_payment_id', 160)->nullable();
            $table->string('provider_reference', 160)->nullable();
            $table->string('provider_status', 48)->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('fee_minor')->nullable();
            $table->string('currency', 12)->default('XOF');
            $table->string('status', 32);
            $table->string('idempotency_key', 160);
            $table->text('checkout_url')->nullable();
            $table->json('provider_metadata')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('credited_at')->nullable();
            $table->timestampsTz();
            $table->unique(['wallet_id', 'idempotency_key']);
            $table->unique(['provider', 'provider_reference']);
            $table->index(['wallet_id', 'created_at']);
            $table->index(['provider', 'status', 'created_at']);
        });

        Schema::create('wallet_provider_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('provider', 48);
            $table->string('event_key', 190);
            $table->string('event_name', 80);
            $table->string('payment_reference', 160)->nullable();
            $table->char('payload_hash', 64);
            $table->string('status', 24)->default('received');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->json('payload');
            $table->text('last_error')->nullable();
            $table->timestampTz('occurred_at')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['provider', 'event_key']);
            $table->index(['status', 'created_at']);
            $table->index(['provider', 'payment_reference']);
        });

        Schema::create('wallet_audit_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('wallet_id')->nullable()->constrained('wallets')->restrictOnDelete();
            $table->foreignUlid('actor_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignUlid('actor_space_id')->nullable()->constrained('user_spaces')->nullOnDelete();
            $table->string('action', 160);
            $table->string('outcome', 32);
            $table->string('subject_type', 80)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->string('trace_id', 64)->nullable();
            $table->json('context')->nullable();
            $table->timestampTz('occurred_at');
            $table->index(['wallet_id', 'occurred_at']);
            $table->index(['action', 'occurred_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        $this->installPostgreSqlGuards();
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_audit_events');
        Schema::dropIfExists('wallet_provider_events');
        Schema::dropIfExists('wallet_deposits');
        Schema::dropIfExists('wallet_reservations');
        Schema::dropIfExists('wallet_operations');
        Schema::dropIfExists('wallet_balance_projections');
        Schema::dropIfExists('wallets');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS prevent_wallet_history_mutation()');
        }
    }

    private function installPostgreSqlGuards(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE wallets ADD CONSTRAINT wallets_kind_check CHECK (kind IN ('user', 'advertiser'))");
        DB::statement("ALTER TABLE wallets ADD CONSTRAINT wallets_status_check CHECK (status IN ('active', 'suspended', 'closed'))");
        DB::statement("ALTER TABLE wallet_operations ADD CONSTRAINT wallet_operations_direction_check CHECK (direction IN ('credit', 'debit', 'internal'))");
        DB::statement('ALTER TABLE wallet_operations ADD CONSTRAINT wallet_operations_amount_check CHECK (amount_minor > 0)');
        DB::statement('ALTER TABLE wallet_reservations ADD CONSTRAINT wallet_reservations_amount_check CHECK (amount_minor > 0)');
        DB::statement("ALTER TABLE wallet_reservations ADD CONSTRAINT wallet_reservations_status_check CHECK (status IN ('active', 'captured', 'released', 'expired'))");
        DB::statement('ALTER TABLE wallet_deposits ADD CONSTRAINT wallet_deposits_amount_check CHECK (amount_minor > 0)');
        DB::statement("ALTER TABLE wallet_deposits ADD CONSTRAINT wallet_deposits_status_check CHECK (status IN ('created', 'creating_payment', 'awaiting_payment', 'payment_detected', 'confirmed', 'credited', 'failed', 'cancelled', 'expired', 'unknown', 'reversed'))");
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_wallet_history_mutation()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Published Wallet history is immutable';
            END;
            $$ LANGUAGE plpgsql;
        SQL);
        DB::statement('CREATE TRIGGER wallet_operations_immutable BEFORE UPDATE OR DELETE ON wallet_operations FOR EACH ROW EXECUTE FUNCTION prevent_wallet_history_mutation()');
        DB::statement('CREATE TRIGGER wallet_audit_events_immutable BEFORE UPDATE OR DELETE ON wallet_audit_events FOR EACH ROW EXECUTE FUNCTION prevent_wallet_history_mutation()');
    }
};
