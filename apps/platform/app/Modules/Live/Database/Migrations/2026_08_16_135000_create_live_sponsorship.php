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
        Schema::table('lives', function (Blueprint $table): void {
            $table->string('type', 30)->default('standard');
            $table->index(['type', 'status']);
        });

        Schema::create('live_reward_campaigns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('live_id')->unique()->constrained('lives')->cascadeOnDelete();
            $table->foreignUlid('advertiser_organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('created_by_account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('status', 30)->default('draft');
            $table->json('segment_configuration')->nullable();
            $table->timestamps();

            $table->index(['advertiser_organization_id', 'status']);
        });

        Schema::create('live_reward_quotes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('live_reward_campaign_id')->constrained('live_reward_campaigns')->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->string('currency', 3)->default('XOF');
            $table->unsignedBigInteger('budget_amount_minor');
            $table->unsignedBigInteger('wasplex_share_minor');
            $table->unsignedBigInteger('spectator_envelope_minor');
            $table->unsignedInteger('estimated_reach_min')->default(0);
            $table->unsignedInteger('estimated_reach_max')->default(0);
            $table->unsignedSmallInteger('planned_duration_minutes');
            $table->unsignedSmallInteger('block_duration_seconds')->default(300);
            $table->timestamp('quoted_at');
            $table->timestamps();

            $table->index(['live_reward_campaign_id', 'status']);
        });

        Schema::create('live_reward_budget_reservations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('live_reward_campaign_id')->constrained('live_reward_campaigns')->cascadeOnDelete();
            $table->foreignUlid('live_reward_quote_id')->constrained('live_reward_quotes')->restrictOnDelete();
            $table->foreignUlid('advertiser_organization_id')->constrained('organizations')->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 20)->default('reserved');
            $table->string('idempotency_key')->unique();
            $table->ulid('ledger_transaction_id')->nullable();
            $table->timestamp('reserved_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['live_reward_campaign_id', 'status']);
        });

        DB::statement("alter table live_reward_campaigns add constraint live_reward_campaigns_status_check check (status in ('draft', 'quoted', 'funds_reserved', 'cancelled'))");
        DB::statement("alter table live_reward_quotes add constraint live_reward_quotes_status_check check (status in ('active', 'consumed', 'superseded'))");
        DB::statement("alter table live_reward_budget_reservations add constraint live_reward_budget_reservations_status_check check (status in ('reserved', 'released'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('live_reward_budget_reservations');
        Schema::dropIfExists('live_reward_quotes');
        Schema::dropIfExists('live_reward_campaigns');

        Schema::table('lives', function (Blueprint $table): void {
            $table->dropIndex(['type', 'status']);
            $table->dropColumn('type');
        });
    }
};
