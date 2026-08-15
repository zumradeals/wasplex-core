<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_stage_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('live_id')->constrained('lives')->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamp('requested_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignUlid('resolved_by_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->index(['live_id', 'status', 'requested_at'], 'live_stage_live_status_requested_idx');
            $table->index(['account_id', 'live_id', 'status'], 'live_stage_account_live_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_stage_requests');
    }
};
