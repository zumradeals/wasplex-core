<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_reward_attention_states', function (Blueprint $table): void {
            $table->foreignUlid('account_session_id')->nullable()->after('viewer_session_id')
                ->constrained('account_sessions')->nullOnDelete();
            $table->foreignUlid('device_id')->nullable()->after('account_session_id')
                ->constrained('account_devices')->nullOnDelete();
            $table->boolean('risk_hold_required')->default(false)->after('risk_signal_count');
            $table->index(['account_id', 'last_heartbeat_at'], 'live_reward_attention_states_account_recent_idx');
        });

        DB::statement('alter table live_reward_attention_blocks drop constraint if exists live_reward_attention_blocks_status_check');
        Schema::table('live_reward_attention_blocks', function (Blueprint $table): void {
            $table->string('risk_mode', 20)->default('observe')->after('gross_amount_minor');
        });
        DB::statement("alter table live_reward_attention_blocks add constraint live_reward_attention_blocks_status_check check (status in ('captured','held','rejected'))");
        DB::statement("alter table live_reward_attention_blocks add constraint live_reward_attention_blocks_risk_mode_check check (risk_mode in ('observe','enforce'))");

        Schema::table('live_reward_budget_reservations', function (Blueprint $table): void {
            $table->unsignedBigInteger('released_amount_minor')->default(0)->after('amount_minor');
        });

        Schema::create('live_reward_risk_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('live_reward_campaign_id')->constrained('live_reward_campaigns')->cascadeOnDelete();
            $table->foreignUlid('live_id')->constrained('lives')->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignUlid('live_reward_attention_state_id')->nullable()
                ->constrained('live_reward_attention_states')->nullOnDelete();
            $table->foreignUlid('account_session_id')->nullable()->constrained('account_sessions')->nullOnDelete();
            $table->foreignUlid('device_id')->nullable()->constrained('account_devices')->nullOnDelete();
            $table->string('signal_code', 60);
            $table->string('severity', 20);
            $table->string('mode', 20);
            $table->jsonb('evidence')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['live_id', 'occurred_at']);
            $table->index(['account_id', 'occurred_at']);
            $table->index(['severity', 'occurred_at']);
        });
        DB::statement("alter table live_reward_risk_events add constraint live_reward_risk_events_severity_check check (severity in ('low','medium','high','critical'))");
        DB::statement("alter table live_reward_risk_events add constraint live_reward_risk_events_mode_check check (mode in ('observe','enforce'))");

        Schema::create('live_reward_holds', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('live_reward_attention_block_id')->unique()
                ->constrained('live_reward_attention_blocks')->cascadeOnDelete();
            $table->foreignUlid('live_reward_campaign_id')->constrained('live_reward_campaigns')->cascadeOnDelete();
            $table->foreignUlid('live_id')->constrained('lives')->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained('accounts')->restrictOnDelete();
            $table->unsignedSmallInteger('block_index');
            $table->unsignedBigInteger('reward_minor');
            $table->unsignedBigInteger('gross_amount_minor');
            $table->string('status', 20)->default('pending');
            $table->jsonb('risk_codes');
            $table->jsonb('evidence')->nullable();
            $table->foreignUlid('reviewed_by_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('resolution_note', 500)->nullable();
            $table->ulid('ledger_transaction_id')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'opened_at']);
            $table->index(['live_reward_campaign_id', 'status']);
            $table->index(['live_id', 'status']);
        });
        DB::statement("alter table live_reward_holds add constraint live_reward_holds_status_check check (status in ('pending','released','rejected'))");

        $this->cloneCapability('admin.feed.dashboard.view', 'admin.live.dashboard.view');
        $this->cloneCapability('admin.feed.risk.review', 'admin.live.risk.review');
    }

    public function down(): void
    {
        DB::table('capability_grants')
            ->whereIn('capability_code', ['admin.live.dashboard.view', 'admin.live.risk.review'])
            ->delete();

        Schema::dropIfExists('live_reward_holds');
        Schema::dropIfExists('live_reward_risk_events');

        Schema::table('live_reward_budget_reservations', function (Blueprint $table): void {
            $table->dropColumn('released_amount_minor');
        });

        DB::statement('alter table live_reward_attention_blocks drop constraint if exists live_reward_attention_blocks_status_check');
        DB::statement('alter table live_reward_attention_blocks drop constraint if exists live_reward_attention_blocks_risk_mode_check');
        Schema::table('live_reward_attention_blocks', function (Blueprint $table): void {
            $table->dropColumn('risk_mode');
        });
        DB::statement("alter table live_reward_attention_blocks add constraint live_reward_attention_blocks_status_check check (status in ('captured'))");

        Schema::table('live_reward_attention_states', function (Blueprint $table): void {
            $table->dropIndex('live_reward_attention_states_account_recent_idx');
            $table->dropForeign(['account_session_id']);
            $table->dropForeign(['device_id']);
            $table->dropColumn(['account_session_id', 'device_id', 'risk_hold_required']);
        });
    }

    private function cloneCapability(string $source, string $destination): void
    {
        DB::table('capability_grants')
            ->where('capability_code', $source)
            ->orderBy('id')
            ->get()
            ->each(function (object $grant) use ($destination): void {
                $exists = DB::table('capability_grants')
                    ->where('account_id', $grant->account_id)
                    ->where('capability_code', $destination)
                    ->where('scope_type', $grant->scope_type)
                    ->where('scope_id', $grant->scope_id)
                    ->exists();

                if ($exists) {
                    return;
                }

                DB::table('capability_grants')->insert([
                    'id' => (string) Str::ulid(),
                    'account_id' => $grant->account_id,
                    'capability_code' => $destination,
                    'scope_type' => $grant->scope_type,
                    'scope_id' => $grant->scope_id,
                    'status' => $grant->status,
                    'starts_at' => $grant->starts_at,
                    'expires_at' => $grant->expires_at,
                    'granted_by' => $grant->granted_by,
                    'revoked_at' => $grant->revoked_at,
                    'revoked_by' => $grant->revoked_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }
};
