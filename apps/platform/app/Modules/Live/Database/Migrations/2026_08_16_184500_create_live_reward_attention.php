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
        Schema::create('live_reward_attention_states', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('live_reward_campaign_id')->constrained('live_reward_campaigns')->cascadeOnDelete();
            $table->foreignUlid('live_id')->constrained('lives')->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignUlid('live_reward_seat_id')->nullable()->constrained('live_reward_seats')->nullOnDelete();
            $table->foreignUlid('viewer_session_id')->nullable()->constrained('live_viewer_sessions')->nullOnDelete();
            $table->unsignedSmallInteger('validated_blocks')->default(0);
            $table->unsignedBigInteger('current_block_ms')->default(0);
            $table->unsignedInteger('risk_signal_count')->default(0);
            $table->boolean('last_heartbeat_qualified')->default(false);
            $table->string('last_risk_signal_code', 60)->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('last_qualified_at')->nullable();
            $table->timestamps();

            $table->unique(['live_id', 'account_id']);
            $table->index(['live_reward_campaign_id', 'validated_blocks']);
        });

        Schema::create('live_reward_attention_blocks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('live_reward_campaign_id')->constrained('live_reward_campaigns')->cascadeOnDelete();
            $table->foreignUlid('live_reward_quote_id')->constrained('live_reward_quotes')->restrictOnDelete();
            $table->foreignUlid('live_id')->constrained('lives')->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignUlid('live_reward_seat_id')->nullable()->constrained('live_reward_seats')->nullOnDelete();
            $table->foreignUlid('viewer_session_id')->nullable()->constrained('live_viewer_sessions')->nullOnDelete();
            $table->unsignedSmallInteger('block_index');
            $table->unsignedBigInteger('attention_ms');
            $table->unsignedBigInteger('reward_minor');
            $table->unsignedBigInteger('gross_amount_minor');
            $table->string('status', 20)->default('captured');
            $table->ulid('ledger_transaction_id')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->unique(['live_id', 'account_id', 'block_index']);
            $table->index(['live_reward_campaign_id', 'status']);
            $table->index(['live_id', 'captured_at']);
        });

        DB::statement("alter table live_reward_attention_blocks add constraint live_reward_attention_blocks_status_check check (status in ('captured'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('live_reward_attention_blocks');
        Schema::dropIfExists('live_reward_attention_states');
    }
};
