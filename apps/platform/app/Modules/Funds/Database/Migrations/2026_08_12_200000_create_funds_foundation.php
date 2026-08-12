<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_programs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('status')->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('fund_program_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('fund_program_id')->constrained('fund_programs')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('currency', 3)->default('XOF');
            $table->unsignedBigInteger('membership_fee_minor')->default(0);
            $table->unsignedInteger('duration_days')->default(365);
            $table->unsignedInteger('minimum_subscription_age_days')->default(0);
            $table->unsignedInteger('max_active_wishes')->default(1);
            $table->unsignedInteger('max_wishes_per_period')->default(1);
            $table->unsignedBigInteger('max_wish_amount_minor')->nullable();
            $table->unsignedInteger('personal_contribution_percent')->default(0);
            $table->unsignedBigInteger('min_debit_minor')->default(0);
            $table->unsignedBigInteger('max_debit_minor')->nullable();
            $table->unsignedBigInteger('daily_cap_minor')->nullable();
            $table->unsignedBigInteger('monthly_cap_minor')->nullable();
            $table->unsignedBigInteger('annual_cap_minor')->nullable();
            $table->unsignedBigInteger('wasplex_fee_minor')->default(0);
            $table->unsignedInteger('notice_hours')->default(24);
            $table->unsignedInteger('grace_period_days')->default(7);
            $table->json('eligible_subscription_classes')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['fund_program_id', 'version']);
        });

        Schema::create('fund_wish_categories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_sensitive_documents')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('fund_memberships', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUlid('fund_program_id')->constrained('fund_programs');
            $table->foreignUlid('program_version_id')->constrained('fund_program_versions');
            $table->foreignUlid('user_subscription_id')->constrained('user_subscriptions');
            $table->string('status')->default('active');
            $table->timestamp('started_at');
            $table->timestamp('grace_started_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspension_reason')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'status']);
        });

        Schema::create('fund_mandates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('fund_membership_id')->constrained('fund_memberships')->cascadeOnDelete();
            $table->unsignedBigInteger('personal_cap_minor');
            $table->boolean('is_active')->default(true);
            $table->string('terms_version');
            $table->timestamp('accepted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->json('accepted_snapshot');
            $table->timestamps();
        });

        Schema::create('fund_wishes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUlid('fund_membership_id')->constrained('fund_memberships');
            $table->foreignUlid('category_id')->constrained('fund_wish_categories');
            $table->string('status')->default('draft');
            $table->string('title');
            $table->text('description');
            $table->string('country_code', 2)->default('CI');
            $table->string('city')->nullable();
            $table->unsignedBigInteger('estimated_amount_minor')->nullable();
            $table->string('currency', 3)->default('XOF');
            $table->unsignedBigInteger('required_personal_contribution_minor')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUlid('reviewed_by_account_id')->nullable()->constrained('accounts');
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'status']);
            $table->index(['status', 'submitted_at']);
        });

        Schema::create('fund_audit_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->nullable()->constrained('accounts');
            $table->string('event_type');
            $table->string('subject_type');
            $table->ulid('subject_id');
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_audit_events');
        Schema::dropIfExists('fund_wishes');
        Schema::dropIfExists('fund_mandates');
        Schema::dropIfExists('fund_memberships');
        Schema::dropIfExists('fund_wish_categories');
        Schema::dropIfExists('fund_program_versions');
        Schema::dropIfExists('fund_programs');
    }
};
