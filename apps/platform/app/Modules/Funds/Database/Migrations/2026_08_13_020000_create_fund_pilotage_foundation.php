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
            $table->unsignedSmallInteger('emergency_queue_share_percent')->default(20);
            $table->unsignedBigInteger('reserve_min_balance_minor')->default(0);
            $table->unsignedSmallInteger('reciprocity_min_score')->default(0);
            $table->unsignedSmallInteger('rehabilitation_incident_threshold')->default(3);
        });

        Schema::create('fund_reciprocity_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->unique()->constrained('accounts')->cascadeOnDelete();
            $table->unsignedSmallInteger('score')->default(0);
            $table->string('level', 32)->default('starting');
            $table->jsonb('factors');
            $table->timestampTz('calculated_at');
            $table->timestampsTz();
            $table->index(['level', 'score']);
        });

        Schema::create('fund_wish_queue_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('fund_wish_id')->unique()->constrained('fund_wishes')->cascadeOnDelete();
            $table->foreignUlid('fund_program_id')->constrained('fund_programs')->restrictOnDelete();
            $table->foreignUlid('program_version_id')->constrained('fund_program_versions')->restrictOnDelete();
            $table->string('lane', 24)->default('ordinary');
            $table->bigInteger('priority_score')->default(0);
            $table->string('status', 24)->default('queued');
            $table->text('blocked_reason')->nullable();
            $table->timestampTz('queued_at');
            $table->timestampTz('released_at')->nullable();
            $table->foreignUlid('queued_by_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignUlid('released_by_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['fund_program_id', 'status', 'lane', 'priority_score'], 'fund_queue_order_idx');
        });

        Schema::create('fund_reserve_allocations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('fund_program_id')->constrained('fund_programs')->restrictOnDelete();
            $table->foreignUlid('fund_wish_id')->nullable()->constrained('fund_wishes')->nullOnDelete();
            $table->foreignUlid('fund_order_id')->nullable()->constrained('fund_orders')->nullOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('consumed_minor')->default(0);
            $table->string('status', 32)->default('authorized');
            $table->string('reason', 190);
            $table->text('justification')->nullable();
            $table->foreignUlid('authorized_by_account_id')->constrained('accounts')->restrictOnDelete();
            $table->timestampTz('authorized_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->timestampsTz();
            $table->index(['fund_program_id', 'status']);
            $table->index(['fund_wish_id', 'status']);
        });

        Schema::create('fund_rehabilitation_cases', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('fund_membership_id')->constrained('fund_memberships')->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('status', 32)->default('required');
            $table->string('trigger_code', 64);
            $table->unsignedSmallInteger('incident_count')->default(0);
            $table->jsonb('required_actions');
            $table->text('decision_note')->nullable();
            $table->timestampTz('opened_at');
            $table->timestampTz('completed_at')->nullable();
            $table->foreignUlid('decided_by_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['account_id', 'status']);
            $table->index(['fund_membership_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_rehabilitation_cases');
        Schema::dropIfExists('fund_reserve_allocations');
        Schema::dropIfExists('fund_wish_queue_entries');
        Schema::dropIfExists('fund_reciprocity_profiles');

        Schema::table('fund_program_versions', function (Blueprint $table): void {
            $table->dropColumn([
                'emergency_queue_share_percent',
                'reserve_min_balance_minor',
                'reciprocity_min_score',
                'rehabilitation_incident_threshold',
            ]);
        });
    }
};
