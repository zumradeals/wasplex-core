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
        Schema::create('reconciliation_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('run_id')->constrained('reconciliation_runs')->cascadeOnDelete();

            // Not foreign keys: cross-module references by value
            // (docs/CLAUDE.md §6) to the source module's own payment row.
            $table->string('source_module');
            $table->string('source_record_id');

            $table->string('provider_reference')->nullable();
            $table->bigInteger('amount_minor')->nullable();
            $table->string('currency')->nullable();
            $table->string('internal_status');
            $table->string('provider_status_raw')->nullable();

            // docs/06-wallet-et-grand-livre-wasplex.md §34.
            $table->string('result_code');

            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['source_module', 'source_record_id']);
            $table->index('result_code');
            $table->index('provider_reference');
        });

        DB::statement("alter table reconciliation_entries add constraint reconciliation_entries_result_code_check check (result_code in ('matched', 'partially_matched', 'unmatched', 'duplicate', 'amount_mismatch', 'status_mismatch', 'manual_review'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_entries');
    }
};
