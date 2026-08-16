<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_reward_quotes', function (Blueprint $table): void {
            $table->unsignedInteger('rewarded_seat_capacity')->default(0);
            $table->unsignedSmallInteger('max_blocks_per_viewer')->default(0);
            $table->unsignedInteger('funded_blocks')->default(0);
            $table->unsignedBigInteger('reward_per_block_minor')->default(0);
            $table->unsignedBigInteger('max_reward_per_viewer_minor')->default(0);
            $table->unsignedBigInteger('spectator_envelope_remainder_minor')->default(0);
        });

        Schema::table('live_viewer_sessions', function (Blueprint $table): void {
            $table->string('rewarded_status', 24)->default('not_applicable');
            $table->string('economic_class', 40)->nullable();
            $table->index(['live_id', 'rewarded_status']);
        });

        Schema::create('live_reward_seats', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('live_reward_campaign_id')->constrained('live_reward_campaigns')->cascadeOnDelete();
            $table->foreignUlid('live_id')->constrained('lives')->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignUlid('viewer_session_id')->nullable()->constrained('live_viewer_sessions')->nullOnDelete();
            $table->string('economic_class', 40);
            $table->string('status', 20)->default('active');
            $table->timestamp('activated_at');
            $table->timestamp('released_at')->nullable();
            $table->string('release_reason', 60)->nullable();
            $table->timestamps();

            $table->unique(['live_id', 'account_id']);
            $table->index(['live_id', 'status']);
        });

        Schema::create('live_reward_waitlist_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('live_reward_campaign_id')->constrained('live_reward_campaigns')->cascadeOnDelete();
            $table->foreignUlid('live_id')->constrained('lives')->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('economic_class', 40);
            $table->string('status', 20)->default('waiting');
            $table->timestamp('queued_at');
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('offer_expires_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['live_id', 'account_id']);
            $table->index(['live_id', 'status', 'queued_at']);
        });

        DB::statement("alter table live_reward_seats add constraint live_reward_seats_status_check check (status in ('active', 'released', 'completed'))");
        DB::statement("alter table live_reward_waitlist_entries add constraint live_reward_waitlist_status_check check (status in ('waiting', 'offered', 'accepted', 'declined', 'expired', 'cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('live_reward_waitlist_entries');
        Schema::dropIfExists('live_reward_seats');

        Schema::table('live_viewer_sessions', function (Blueprint $table): void {
            $table->dropIndex(['live_id', 'rewarded_status']);
            $table->dropColumn(['rewarded_status', 'economic_class']);
        });

        Schema::table('live_reward_quotes', function (Blueprint $table): void {
            $table->dropColumn([
                'rewarded_seat_capacity',
                'max_blocks_per_viewer',
                'funded_blocks',
                'reward_per_block_minor',
                'max_reward_per_viewer_minor',
                'spectator_envelope_remainder_minor',
            ]);
        });
    }
};
