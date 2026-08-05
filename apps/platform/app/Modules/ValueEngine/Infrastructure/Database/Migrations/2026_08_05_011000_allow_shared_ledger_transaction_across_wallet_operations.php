<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_operations', function (Blueprint $table): void {
            $table->dropUnique('wallet_operations_ledger_transaction_id_unique');
            $table->unique(
                ['wallet_id', 'ledger_transaction_id'],
                'wallet_operations_wallet_ledger_transaction_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('wallet_operations', function (Blueprint $table): void {
            $table->dropUnique('wallet_operations_wallet_ledger_transaction_unique');
            $table->unique(
                'ledger_transaction_id',
                'wallet_operations_ledger_transaction_id_unique',
            );
        });
    }
};
