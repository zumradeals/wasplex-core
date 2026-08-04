<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_review_cases', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('campaign_id');
            $table->ulid('campaign_version_id');
            $table->unsignedInteger('submission_number');
            $table->string('status', 32)->default('pending');
            $table->ulid('submitted_by_account_id')->nullable();
            $table->ulid('assigned_to_account_id')->nullable();
            $table->ulid('decided_by_account_id')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->foreign('campaign_version_id')->references('id')->on('campaign_versions')->restrictOnDelete();
            $table->foreign('submitted_by_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('assigned_to_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('decided_by_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->unique(['campaign_id', 'submission_number'], 'campaign_review_submission_unique');
            $table->index(['status', 'opened_at']);
        });

        Schema::create('campaign_review_tasks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('review_case_id')->unique();
            $table->ulid('campaign_id');
            $table->string('status', 24)->default('pending');
            $table->string('priority', 16)->default('normal');
            $table->ulid('assigned_to_account_id')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('review_case_id')->references('id')->on('campaign_review_cases')->cascadeOnDelete();
            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->foreign('assigned_to_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->index(['status', 'priority', 'created_at']);
        });

        Schema::create('campaign_review_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('review_case_id');
            $table->ulid('campaign_id');
            $table->ulid('actor_account_id')->nullable();
            $table->string('type', 48);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->foreign('review_case_id')->references('id')->on('campaign_review_cases')->cascadeOnDelete();
            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->foreign('actor_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->index(['campaign_id', 'occurred_at']);
            $table->index(['type', 'occurred_at']);
        });

        Schema::create('campaign_status_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('campaign_id');
            $table->ulid('actor_account_id')->nullable();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->foreign('actor_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->index(['campaign_id', 'occurred_at']);
            $table->index(['to_status', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_status_events');
        Schema::dropIfExists('campaign_review_events');
        Schema::dropIfExists('campaign_review_tasks');
        Schema::dropIfExists('campaign_review_cases');
    }
};
