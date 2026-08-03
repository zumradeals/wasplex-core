<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('economic_classes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('economic_class_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('economic_class_id')->constrained('economic_classes')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('state');
            $table->string('public_name');
            $table->unsignedInteger('quota_monthly');
            $table->unsignedInteger('weight_basis_points');
            $table->unsignedInteger('targeting_coefficient_basis_points')->default(10000);
            $table->jsonb('features')->nullable();
            $table->timestampTz('effective_from')->nullable();
            $table->timestampTz('effective_to')->nullable();
            $table->uuid('created_by_account_id')->nullable();
            $table->uuid('approved_by_account_id')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->uuid('published_by_account_id')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->uuid('suspended_by_account_id')->nullable();
            $table->string('suspension_reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['economic_class_id', 'version']);
        });

        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->foreignUuid('economic_class_id')->constrained('economic_classes')->restrictOnDelete();
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('subscription_plan_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('state');
            $table->string('public_name');
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->char('currency', 3)->default('XOF');
            $table->unsignedInteger('duration_days')->nullable();
            $table->jsonb('entitlements')->nullable();
            $table->timestampTz('effective_from')->nullable();
            $table->timestampTz('effective_to')->nullable();
            $table->timestamps();
            $table->unique(['subscription_plan_id', 'version']);
        });

        Schema::create('user_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id')->index();
            $table->foreignUuid('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->foreignUuid('economic_class_id')->constrained('economic_classes')->restrictOnDelete();
            $table->string('status');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_quota_counters', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_subscription_id')->constrained('user_subscriptions')->cascadeOnDelete();
            $table->date('cycle_start');
            $table->date('cycle_end');
            $table->unsignedInteger('quota_limit');
            $table->unsignedInteger('consumed')->default(0);
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
            $table->unique(['user_subscription_id', 'cycle_start']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE economic_class_versions ADD CONSTRAINT economic_class_versions_state_check CHECK (state IN ('draft','approved','published','suspended'))");
            DB::statement('ALTER TABLE economic_class_versions ADD CONSTRAINT economic_class_weights_check CHECK (weight_basis_points <= 10000)');
            DB::statement('ALTER TABLE economic_class_versions ADD CONSTRAINT economic_class_effective_period_check CHECK (effective_to IS NULL OR effective_from IS NULL OR effective_to > effective_from)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_quota_counters');
        Schema::dropIfExists('user_subscriptions');
        Schema::dropIfExists('subscription_plan_versions');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('economic_class_versions');
        Schema::dropIfExists('economic_classes');
    }
};
