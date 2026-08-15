<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lives', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('title', 140);
            $table->text('description')->nullable();
            $table->string('category', 60)->default('general');
            $table->string('language', 10)->default('fr');
            $table->string('visibility', 20)->default('public');
            $table->string('status', 30)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->unsignedSmallInteger('planned_duration_minutes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('replay_policy', 20)->default('disabled');
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index(['owner_account_id', 'created_at']);
            $table->index(['visibility', 'status']);
        });

        Schema::create('live_stream_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('live_id')->constrained('lives')->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->string('provider', 40)->default('pending_adapter');
            $table->string('provider_session_reference', 190)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['live_id', 'status']);
        });

        Schema::create('live_viewer_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('live_id')->constrained('lives')->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('status', 20)->default('watching');
            $table->timestamp('joined_at');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->index(['live_id', 'status']);
            $table->index(['account_id', 'created_at']);
        });

        Schema::create('live_audit_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('live_id')->constrained('lives')->cascadeOnDelete();
            $table->foreignUlid('actor_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('event_type', 80);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['live_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_audit_events');
        Schema::dropIfExists('live_viewer_sessions');
        Schema::dropIfExists('live_stream_sessions');
        Schema::dropIfExists('lives');
    }
};
