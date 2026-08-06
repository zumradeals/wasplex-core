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
        Schema::create('campaign_budget_reservations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignUlid('campaign_quote_id')->constrained('campaign_quotes');
            $table->string('organization_id')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->string('status')->default('reserved');
            $table->string('idempotency_key')->unique();
            $table->ulid('ledger_transaction_id')->nullable();
            $table->timestamps();
        });

        DB::statement("alter table campaign_budget_reservations add constraint campaign_budget_reservations_status_check check (status in ('reserved', 'released'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_budget_reservations');
    }
};
