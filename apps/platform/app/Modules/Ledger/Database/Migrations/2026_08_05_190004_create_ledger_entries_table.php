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
        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('transaction_id')->constrained('ledger_transactions')->restrictOnDelete();
            $table->foreignUlid('account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->string('direction');
            $table->bigInteger('amount_minor');
            $table->string('currency');
            $table->string('description')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['account_id', 'created_at']);
            $table->index('transaction_id');
        });

        DB::statement('alter table ledger_entries add constraint ledger_entries_amount_minor_positive check (amount_minor > 0)');
        DB::statement("alter table ledger_entries add constraint ledger_entries_direction_valid check (direction in ('debit', 'credit'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
