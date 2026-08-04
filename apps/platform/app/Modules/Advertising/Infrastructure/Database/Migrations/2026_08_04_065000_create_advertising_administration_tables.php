<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising_configuration_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->unsignedInteger('version')->unique();
            $table->string('state', 24)->default('published')->index();
            $table->unsignedInteger('minimum_segment_size');
            $table->unsignedInteger('estimate_rounding_step');
            $table->unsignedInteger('frequency_window_hours');
            $table->unsignedInteger('frequency_limit');
            $table->unsignedInteger('fatigue_limit');
            $table->foreignUlid('created_by_account_id')->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            $table->foreignUlid('published_by_account_id')->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_to')->nullable();
            $table->timestampTz('published_at');
            $table->text('suspension_reason')->nullable();
            $table->timestampsTz();
            $table->index(
                ['state', 'effective_from', 'effective_to'],
                'advertising_configuration_effective_index',
            );
        });

        Schema::create('advertising_administration_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('actor_account_id')->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            $table->string('event_type', 100)->index();
            $table->string('resource_type', 80);
            $table->ulid('resource_id')->nullable();
            $table->string('resource_code', 180)->nullable();
            $table->string('before_state', 24)->nullable();
            $table->string('after_state', 24)->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampsTz();
            $table->index(
                ['resource_type', 'resource_id', 'occurred_at'],
                'advertising_admin_resource_history_index',
            );
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE advertising_configuration_versions ADD CONSTRAINT advertising_configuration_state_allowed CHECK (state IN ('published','suspended'))");
            DB::statement('ALTER TABLE advertising_configuration_versions ADD CONSTRAINT advertising_configuration_minimum_segment_positive CHECK (minimum_segment_size > 0)');
            DB::statement('ALTER TABLE advertising_configuration_versions ADD CONSTRAINT advertising_configuration_rounding_positive CHECK (estimate_rounding_step > 0)');
            DB::statement('ALTER TABLE advertising_configuration_versions ADD CONSTRAINT advertising_configuration_frequency_window_positive CHECK (frequency_window_hours > 0)');
            DB::statement('ALTER TABLE advertising_configuration_versions ADD CONSTRAINT advertising_configuration_frequency_limit_positive CHECK (frequency_limit > 0)');
            DB::statement('ALTER TABLE advertising_configuration_versions ADD CONSTRAINT advertising_configuration_fatigue_limit_positive CHECK (fatigue_limit > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising_administration_events');
        Schema::dropIfExists('advertising_configuration_versions');
    }
};
