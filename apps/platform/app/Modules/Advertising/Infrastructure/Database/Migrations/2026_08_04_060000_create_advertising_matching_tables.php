<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising_segments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignUlid('campaign_version_id')->constrained('campaign_versions')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 24)->default('active')->index();
            $table->string('rule_version', 64);
            $table->unsignedInteger('minimum_size')->default(25);
            $table->foreignUlid('created_by_account_id')->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            $table->timestampsTz();
            $table->unique(
                ['campaign_version_id', 'version'],
                'advertising_segments_version_unique',
            );
            $table->index(
                ['campaign_id', 'status'],
                'advertising_segments_campaign_status_index',
            );
        });

        Schema::create('advertising_segment_rules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('advertising_segment_id')
                ->constrained('advertising_segments')
                ->cascadeOnDelete();
            $table->foreignUlid('advertising_taxonomy_id')
                ->constrained('advertising_taxonomies')
                ->restrictOnDelete();
            $table->string('operator', 24)->default('equals');
            $table->json('expected_values');
            $table->boolean('required')->default(true);
            $table->json('purpose_codes');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->unique(
                ['advertising_segment_id', 'advertising_taxonomy_id'],
                'advertising_segment_rule_taxonomy_unique',
            );
        });

        Schema::create('advertising_segment_estimates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('advertising_segment_id')
                ->constrained('advertising_segments')
                ->cascadeOnDelete();
            $table->string('estimate_key', 64)->unique();
            $table->string('status', 24);
            $table->unsignedBigInteger('raw_count');
            $table->unsignedBigInteger('protected_min')->nullable();
            $table->unsignedBigInteger('protected_max')->nullable();
            $table->unsignedBigInteger('rounded_count')->nullable();
            $table->unsignedInteger('threshold');
            $table->string('bucket', 48);
            $table->string('reason_code', 80)->nullable();
            $table->foreignUlid('queried_by_account_id')->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            $table->timestampTz('estimated_at');
            $table->json('metadata')->nullable();
            $table->timestampsTz();
            $table->index(
                ['advertising_segment_id', 'estimated_at'],
                'advertising_segment_estimate_history_index',
            );
        });

        Schema::create('advertising_frequency_counters', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUlid('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->timestampTz('window_start');
            $table->timestampTz('window_end');
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('fatigue_score')->default(0);
            $table->timestampTz('last_impression_at')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestampsTz();
            $table->unique(
                ['account_id', 'campaign_id', 'window_start'],
                'advertising_frequency_window_unique',
            );
        });

        Schema::create('advertising_matches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUlid('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignUlid('campaign_version_id')->constrained('campaign_versions')->restrictOnDelete();
            $table->foreignUlid('advertising_segment_id')
                ->constrained('advertising_segments')
                ->restrictOnDelete();
            $table->string('idempotency_key', 64)->unique();
            $table->string('decision', 24);
            $table->unsignedInteger('score_basis_points')->default(0);
            $table->string('score_band', 24)->nullable();
            $table->string('rule_version', 64);
            $table->json('explanation_tokens');
            $table->json('exclusion_codes');
            $table->json('frequency_state');
            $table->timestampTz('evaluated_at');
            $table->timestampsTz();
            $table->index(
                ['account_id', 'decision', 'evaluated_at'],
                'advertising_matches_account_decision_index',
            );
            $table->index(
                ['campaign_id', 'decision', 'evaluated_at'],
                'advertising_matches_campaign_decision_index',
            );
        });

        Schema::create('advertising_match_audits', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('advertising_match_id')->nullable()
                ->constrained('advertising_matches')
                ->nullOnDelete();
            $table->foreignUlid('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->string('subject_hash', 64);
            $table->string('decision', 24);
            $table->json('reason_codes');
            $table->string('score_band', 24)->nullable();
            $table->timestampTz('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestampsTz();
            $table->index(
                ['campaign_id', 'decision', 'occurred_at'],
                'advertising_match_audit_campaign_index',
            );
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE advertising_segments ADD CONSTRAINT advertising_segment_status_allowed CHECK (status IN ('active','suspended','archived'))");
            DB::statement("ALTER TABLE advertising_segment_rules ADD CONSTRAINT advertising_segment_operator_allowed CHECK (operator IN ('equals','in'))");
            DB::statement("ALTER TABLE advertising_segment_estimates ADD CONSTRAINT advertising_estimate_status_allowed CHECK (status IN ('available','withheld'))");
            DB::statement("ALTER TABLE advertising_matches ADD CONSTRAINT advertising_match_decision_allowed CHECK (decision IN ('eligible','ineligible','withheld'))");
            DB::statement('ALTER TABLE advertising_matches ADD CONSTRAINT advertising_match_score_range CHECK (score_basis_points <= 10000)');
            DB::statement('ALTER TABLE advertising_segments ADD CONSTRAINT advertising_segment_minimum_size_positive CHECK (minimum_size > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising_match_audits');
        Schema::dropIfExists('advertising_matches');
        Schema::dropIfExists('advertising_frequency_counters');
        Schema::dropIfExists('advertising_segment_estimates');
        Schema::dropIfExists('advertising_segment_rules');
        Schema::dropIfExists('advertising_segments');
    }
};
