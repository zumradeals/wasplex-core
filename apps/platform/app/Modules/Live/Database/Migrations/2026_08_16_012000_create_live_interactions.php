<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_comments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('live_id')->constrained('lives')->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained('accounts')->restrictOnDelete();
            $table->ulid('parent_comment_id')->nullable();
            $table->string('body', 300);
            $table->timestamp('pinned_at')->nullable();
            $table->foreignUlid('pinned_by_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamp('hidden_at')->nullable();
            $table->foreignUlid('hidden_by_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->index(['live_id', 'created_at']);
            $table->index(['live_id', 'pinned_at']);
            $table->index(['live_id', 'hidden_at']);
            $table->index(['account_id', 'created_at']);
        });

        Schema::table('live_comments', function (Blueprint $table): void {
            $table->foreign('parent_comment_id')
                ->references('id')
                ->on('live_comments')
                ->nullOnDelete();
        });

        Schema::create('live_comment_reports', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('live_id')->constrained('lives')->cascadeOnDelete();
            $table->foreignUlid('comment_id')->constrained('live_comments')->cascadeOnDelete();
            $table->foreignUlid('reporter_account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('category', 40)->default('other');
            $table->string('note', 300)->nullable();
            $table->timestamps();

            $table->unique(['comment_id', 'reporter_account_id']);
            $table->index(['live_id', 'created_at']);
        });

        Schema::create('live_interaction_settings', function (Blueprint $table): void {
            $table->ulid('live_id')->primary();
            $table->foreign('live_id')->references('id')->on('lives')->cascadeOnDelete();
            $table->boolean('comments_enabled')->default(true);
            $table->unsignedSmallInteger('slow_mode_seconds')->default(0);
            $table->foreignUlid('updated_by_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('live_comment_restrictions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('live_id')->constrained('lives')->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained('accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('reason', 200)->nullable();
            $table->foreignUlid('created_by_account_id')->constrained('accounts')->restrictOnDelete();
            $table->timestamp('lifted_at')->nullable();
            $table->foreignUlid('lifted_by_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['live_id', 'account_id']);
            $table->index(['live_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_comment_restrictions');
        Schema::dropIfExists('live_interaction_settings');
        Schema::dropIfExists('live_comment_reports');
        Schema::dropIfExists('live_comments');
    }
};
