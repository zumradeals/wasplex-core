<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_transaction_links', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('transaction_id')->constrained('ledger_transactions')->restrictOnDelete();
            $table->foreignUlid('linked_transaction_id')->constrained('ledger_transactions')->restrictOnDelete();
            $table->string('link_type')->default('reversal');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['transaction_id', 'linked_transaction_id']);
            $table->index('linked_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_transaction_links');
    }
};
