<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_personal_contributions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('fund_wish_id');
            $table->ulid('account_id');
            $table->string('source', 32);
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 8)->default('WP');
            $table->string('idempotency_key', 160)->unique();
            $table->ulid('ledger_transaction_id')->unique();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('fund_wish_id')->references('id')->on('fund_wishes')->cascadeOnDelete();
            $table->foreign('account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign('ledger_transaction_id')->references('id')->on('ledger_transactions')->restrictOnDelete();
            $table->index(['fund_wish_id', 'created_at']);
            $table->index(['account_id', 'created_at']);
        });

        Schema::table('fund_wishes', function (Blueprint $table): void {
            $table->timestampTz('personal_contribution_completed_at')->nullable()->after('required_personal_contribution_minor');
        });
    }

    public function down(): void
    {
        Schema::table('fund_wishes', function (Blueprint $table): void {
            $table->dropColumn('personal_contribution_completed_at');
        });

        Schema::dropIfExists('fund_personal_contributions');
    }
};
