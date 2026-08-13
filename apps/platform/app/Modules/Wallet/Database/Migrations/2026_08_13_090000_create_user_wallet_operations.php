<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_wallet_deposits', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('account_id')->index();
            $table->string('ledger_transaction_id')->nullable()->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 8)->default('XOF');
            $table->string('status', 32)->default('created')->index();
            $table->string('provider_code', 32)->default('geniuspay');
            $table->string('provider_reference')->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->string('idempotency_key')->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('user_wallet_transfers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('sender_account_id')->index();
            $table->string('recipient_account_id')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 8)->default('WP');
            $table->string('status', 32)->default('pending')->index();
            $table->string('ledger_transaction_id')->nullable()->unique();
            $table->string('idempotency_key');
            $table->timestamps();

            $table->unique(
                ['sender_account_id', 'idempotency_key'],
                'user_wallet_transfers_sender_idem_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_wallet_transfers');
        Schema::dropIfExists('user_wallet_deposits');
    }
};
