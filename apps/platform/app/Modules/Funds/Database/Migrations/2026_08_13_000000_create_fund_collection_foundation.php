<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fund_program_versions', function (Blueprint $table): void {
            $table->unsignedInteger('arrears_grace_days')->default(7);
            $table->unsignedInteger('max_simultaneous_collections')->default(1);
        });

        Schema::create('fund_collection_snapshots', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('fund_wish_id')->unique()->constrained('fund_wishes')->cascadeOnDelete();
            $table->foreignUlid('fund_program_id')->constrained('fund_programs')->restrictOnDelete();
            $table->foreignUlid('program_version_id')->constrained('fund_program_versions')->restrictOnDelete();
            $table->char('country_code', 2);
            $table->char('currency', 3)->default('XOF');
            $table->unsignedBigInteger('validated_cost_minor');
            $table->unsignedBigInteger('personal_contribution_minor');
            $table->unsignedBigInteger('partner_contribution_minor')->default(0);
            $table->unsignedBigInteger('reserve_contribution_minor')->default(0);
            $table->unsignedBigInteger('collective_amount_minor');
            $table->unsignedBigInteger('default_wasplex_fee_minor')->default(0);
            $table->unsignedInteger('participant_count');
            $table->unsignedBigInteger('solidarity_base_minor');
            $table->unsignedBigInteger('solidarity_remainder_minor')->default(0);
            $table->string('rule_version', 40)->default('P014-D-v1');
            $table->char('snapshot_hash', 64)->unique();
            $table->jsonb('rules_snapshot');
            $table->string('status', 32)->default('scheduled');
            $table->timestampTz('notice_at');
            $table->timestampTz('scheduled_at');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->foreignUlid('created_by_account_id')->constrained('accounts')->restrictOnDelete();
            $table->timestampsTz();
            $table->index(['fund_program_id', 'country_code', 'currency', 'status'], 'fund_collection_active_idx');
        });

        Schema::create('fund_collection_participants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('snapshot_id')->constrained('fund_collection_snapshots')->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignUlid('fund_membership_id')->constrained('fund_memberships')->restrictOnDelete();
            $table->foreignUlid('fund_mandate_id')->constrained('fund_mandates')->restrictOnDelete();
            $table->unsignedInteger('ordinal');
            $table->unsignedBigInteger('solidarity_due_minor');
            $table->unsignedBigInteger('fee_due_minor')->default(0);
            $table->unsignedBigInteger('total_due_minor');
            $table->unsignedBigInteger('solidarity_paid_minor')->default(0);
            $table->unsignedBigInteger('fee_paid_minor')->default(0);
            $table->unsignedBigInteger('arrears_minor')->default(0);
            $table->unsignedBigInteger('mandate_cap_minor');
            $table->unsignedBigInteger('daily_remaining_minor')->nullable();
            $table->unsignedBigInteger('monthly_remaining_minor')->nullable();
            $table->unsignedBigInteger('annual_remaining_minor')->nullable();
            $table->jsonb('rule_snapshot');
            $table->string('status', 32)->default('pending');
            $table->timestampTz('last_attempted_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampsTz();
            $table->unique(['snapshot_id', 'account_id']);
            $table->index(['account_id', 'status']);
        });

        Schema::create('fund_collection_debits', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('snapshot_id')->constrained('fund_collection_snapshots')->cascadeOnDelete();
            $table->foreignUlid('participant_id')->constrained('fund_collection_participants')->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('idempotency_key', 190)->unique();
            $table->unsignedBigInteger('requested_solidarity_minor');
            $table->unsignedBigInteger('requested_fee_minor')->default(0);
            $table->unsignedBigInteger('debited_solidarity_minor')->default(0);
            $table->unsignedBigInteger('debited_fee_minor')->default(0);
            $table->unsignedBigInteger('arrears_minor')->default(0);
            $table->foreignUlid('ledger_transaction_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
            $table->string('status', 32);
            $table->string('failure_code', 80)->nullable();
            $table->timestampTz('attempted_at');
            $table->timestampsTz();
            $table->index(['snapshot_id', 'status']);
            $table->index(['account_id', 'attempted_at']);
        });

        Schema::create('fund_arrears', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('snapshot_id')->constrained('fund_collection_snapshots')->cascadeOnDelete();
            $table->foreignUlid('participant_id')->unique()->constrained('fund_collection_participants')->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained('accounts')->restrictOnDelete();
            $table->unsignedBigInteger('due_solidarity_minor');
            $table->unsignedBigInteger('settled_solidarity_minor')->default(0);
            $table->string('status', 32)->default('open');
            $table->timestampTz('grace_ends_at')->nullable();
            $table->timestampTz('settled_at')->nullable();
            $table->timestampsTz();
            $table->index(['account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_arrears');
        Schema::dropIfExists('fund_collection_debits');
        Schema::dropIfExists('fund_collection_participants');
        Schema::dropIfExists('fund_collection_snapshots');

        Schema::table('fund_program_versions', function (Blueprint $table): void {
            $table->dropColumn(['arrears_grace_days', 'max_simultaneous_collections']);
        });
    }
};
