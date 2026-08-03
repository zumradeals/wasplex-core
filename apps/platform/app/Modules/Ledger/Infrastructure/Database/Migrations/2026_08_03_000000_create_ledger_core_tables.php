<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_account_types', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name', 120);
            $table->string('normal_balance', 8);
            $table->text('description')->nullable();
            $table->timestampTz('created_at');
        });

        Schema::create('ledger_journals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name', 120);
            $table->string('status', 24)->default('active');
            $table->timestampTz('created_at');
        });

        Schema::create('ledger_accounts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_type_id')->constrained('ledger_account_types')->restrictOnDelete();
            $table->string('code', 160)->unique();
            $table->string('owner_type', 80)->nullable();
            $table->string('owner_id', 64)->nullable();
            $table->string('unit', 12);
            $table->string('currency', 12);
            $table->string('status', 24)->default('active');
            $table->char('country_code', 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestampsTz();
            $table->index(['owner_type', 'owner_id']);
            $table->index(['unit', 'currency', 'status']);
        });

        Schema::create('ledger_transactions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('journal_id')->constrained('ledger_journals')->restrictOnDelete();
            $table->string('type', 80);
            $table->string('status', 24)->default('draft');
            $table->string('source_module', 80);
            $table->string('business_reference', 160);
            $table->string('idempotency_key', 160);
            $table->string('rule_version', 80)->nullable();
            $table->string('unit', 12);
            $table->string('currency', 12);
            $table->unsignedBigInteger('total_minor');
            $table->foreignUlid('created_by_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignUlid('beneficiary_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->text('justification')->nullable();
            $table->json('metadata')->nullable();
            $table->string('trace_id', 64)->nullable();
            $table->timestampTz('posted_at');
            $table->timestampTz('created_at');
            $table->unique(['source_module', 'idempotency_key']);
            $table->index(['source_module', 'business_reference']);
            $table->index(['type', 'status', 'posted_at']);
            $table->index(['unit', 'currency', 'posted_at']);
        });

        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('transaction_id')->constrained('ledger_transactions')->restrictOnDelete();
            $table->foreignUlid('account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->string('direction', 8);
            $table->unsignedBigInteger('amount_minor');
            $table->string('unit', 12);
            $table->string('currency', 12);
            $table->string('description', 500);
            $table->string('external_reference', 160)->nullable();
            $table->timestampTz('posted_at');
            $table->timestampTz('created_at');
            $table->index(['transaction_id', 'direction']);
            $table->index(['account_id', 'posted_at']);
        });

        Schema::create('ledger_transaction_links', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('source_transaction_id')->constrained('ledger_transactions')->restrictOnDelete();
            $table->foreignUlid('target_transaction_id')->constrained('ledger_transactions')->restrictOnDelete();
            $table->string('link_type', 32);
            $table->timestampTz('created_at');
            $table->unique(['source_transaction_id', 'target_transaction_id', 'link_type'], 'ledger_links_unique');
            $table->index(['source_transaction_id', 'link_type']);
            $table->index(['target_transaction_id', 'link_type']);
        });

        Schema::create('ledger_idempotency_keys', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('source_module', 80);
            $table->string('idempotency_key', 160);
            $table->char('payload_hash', 64);
            $table->foreignUlid('transaction_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->timestampTz('created_at');
            $table->unique(['source_module', 'idempotency_key'], 'ledger_idempotency_scope_unique');
        });

        $this->installPostgreSqlGuards();
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_idempotency_keys');
        Schema::dropIfExists('ledger_transaction_links');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('ledger_accounts');
        Schema::dropIfExists('ledger_journals');
        Schema::dropIfExists('ledger_account_types');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS prevent_ledger_mutation()');
            DB::statement('DROP FUNCTION IF EXISTS protect_ledger_idempotency()');
            DB::statement('DROP FUNCTION IF EXISTS protect_ledger_entry()');
            DB::statement('DROP FUNCTION IF EXISTS protect_ledger_transaction()');
        }
    }

    private function installPostgreSqlGuards(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE ledger_account_types ADD CONSTRAINT ledger_account_types_balance_check CHECK (normal_balance IN ('debit', 'credit'))");
        DB::statement("ALTER TABLE ledger_journals ADD CONSTRAINT ledger_journals_status_check CHECK (status IN ('active', 'closed'))");
        DB::statement("ALTER TABLE ledger_accounts ADD CONSTRAINT ledger_accounts_status_check CHECK (status IN ('active', 'suspended', 'closed'))");
        DB::statement("ALTER TABLE ledger_transactions ADD CONSTRAINT ledger_transactions_status_check CHECK (status IN ('draft', 'posted'))");
        DB::statement('ALTER TABLE ledger_transactions ADD CONSTRAINT ledger_transactions_amount_check CHECK (total_minor > 0)');
        DB::statement("ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_direction_check CHECK (direction IN ('debit', 'credit'))");
        DB::statement('ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_amount_check CHECK (amount_minor > 0)');
        DB::statement("ALTER TABLE ledger_transaction_links ADD CONSTRAINT ledger_links_type_check CHECK (link_type IN ('reversal', 'related'))");
        DB::statement('ALTER TABLE ledger_transaction_links ADD CONSTRAINT ledger_links_distinct_check CHECK (source_transaction_id <> target_transaction_id)');
        DB::statement("CREATE UNIQUE INDEX ledger_links_one_reversal_source ON ledger_transaction_links (source_transaction_id) WHERE link_type = 'reversal'");
        DB::statement("CREATE UNIQUE INDEX ledger_links_one_reversal_target ON ledger_transaction_links (target_transaction_id) WHERE link_type = 'reversal'");

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION protect_ledger_transaction()
            RETURNS trigger AS $$
            DECLARE
                debit_total bigint;
                credit_total bigint;
            BEGIN
                IF TG_OP = 'DELETE' OR OLD.status = 'posted' THEN
                    RAISE EXCEPTION 'Published ledger transactions are immutable';
                END IF;

                IF NEW.status <> 'posted' OR (to_jsonb(NEW) - 'status') <> (to_jsonb(OLD) - 'status') THEN
                    RAISE EXCEPTION 'Only draft to posted ledger finalization is allowed';
                END IF;

                SELECT
                    COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount_minor ELSE 0 END), 0),
                    COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount_minor ELSE 0 END), 0)
                INTO debit_total, credit_total
                FROM ledger_entries
                WHERE transaction_id = OLD.id;

                IF debit_total <= 0 OR debit_total <> credit_total OR NEW.total_minor <> debit_total THEN
                    RAISE EXCEPTION 'Ledger transaction is not balanced';
                END IF;

                IF EXISTS (
                    SELECT 1
                    FROM ledger_entries entry
                    JOIN ledger_accounts account ON account.id = entry.account_id
                    WHERE entry.transaction_id = OLD.id
                      AND (
                          entry.unit <> NEW.unit
                          OR entry.currency <> NEW.currency
                          OR account.unit <> NEW.unit
                          OR account.currency <> NEW.currency
                      )
                ) THEN
                    RAISE EXCEPTION 'Ledger transaction mixes account units or currencies';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION protect_ledger_entry()
            RETURNS trigger AS $$
            DECLARE
                transaction_status varchar;
            BEGIN
                IF TG_OP <> 'INSERT' THEN
                    RAISE EXCEPTION 'Published ledger entries are immutable';
                END IF;

                SELECT status INTO transaction_status
                FROM ledger_transactions
                WHERE id = NEW.transaction_id;

                IF transaction_status <> 'draft' THEN
                    RAISE EXCEPTION 'Entries can only be added to a draft ledger transaction';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_ledger_mutation()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Published ledger records are immutable';
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION protect_ledger_idempotency()
            RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'Ledger idempotency keys are immutable';
                END IF;

                IF OLD.transaction_id IS NOT NULL
                   OR NEW.transaction_id IS NULL
                   OR (to_jsonb(NEW) - 'transaction_id') <> (to_jsonb(OLD) - 'transaction_id') THEN
                    RAISE EXCEPTION 'Only initial ledger idempotency consumption is allowed';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared('CREATE TRIGGER ledger_transactions_immutable BEFORE UPDATE OR DELETE ON ledger_transactions FOR EACH ROW EXECUTE FUNCTION protect_ledger_transaction()');
        DB::unprepared('CREATE TRIGGER ledger_entries_guard BEFORE INSERT OR UPDATE OR DELETE ON ledger_entries FOR EACH ROW EXECUTE FUNCTION protect_ledger_entry()');
        DB::unprepared('CREATE TRIGGER ledger_transaction_links_immutable BEFORE UPDATE OR DELETE ON ledger_transaction_links FOR EACH ROW EXECUTE FUNCTION prevent_ledger_mutation()');
        DB::unprepared('CREATE TRIGGER ledger_idempotency_immutable BEFORE UPDATE OR DELETE ON ledger_idempotency_keys FOR EACH ROW EXECUTE FUNCTION protect_ledger_idempotency()');
    }
};
