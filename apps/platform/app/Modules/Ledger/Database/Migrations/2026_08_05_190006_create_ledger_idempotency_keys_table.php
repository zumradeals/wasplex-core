<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_idempotency_keys', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('idempotency_key')->unique();
            $table->string('content_hash', 64);
            $table->foreignUlid('transaction_id')->constrained('ledger_transactions')->restrictOnDelete();
            $table->string('source_module');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_idempotency_keys');
    }
};
