<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_qr_tokens', function (Blueprint $table): void {
            $table->unsignedBigInteger('amount_minor')->nullable()->after('status');
            $table->string('currency', 3)->default('WP')->after('amount_minor');
            $table->string('note', 180)->nullable()->after('currency');
        });

        Schema::create('card_operations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('payer_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignUlid('beneficiary_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignUlid('beneficiary_card_id')->constrained('cards')->restrictOnDelete();
            $table->foreignUlid('qr_token_id')->constrained('card_qr_tokens')->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('WP');
            $table->string('note', 180)->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('idempotency_key', 100);
            $table->foreignUlid('ledger_transaction_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->string('receipt_reference', 32)->unique();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['payer_account_id', 'idempotency_key']);
            $table->index(['payer_account_id', 'created_at']);
            $table->index(['beneficiary_account_id', 'created_at']);
            $table->index(['beneficiary_card_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_operations');

        Schema::table('card_qr_tokens', function (Blueprint $table): void {
            $table->dropColumn(['amount_minor', 'currency', 'note']);
        });
    }
};
