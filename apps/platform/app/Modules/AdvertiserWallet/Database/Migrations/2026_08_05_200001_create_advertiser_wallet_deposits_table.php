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
        Schema::create('advertiser_wallet_deposits', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // Not foreign keys: cross-module references by value
            // (docs/CLAUDE.md #6). organization_id -> Identity Organization,
            // initiated_by -> Identity Account, ledger_transaction_id ->
            // Ledger's ledger_transactions (P002), set only once credited.
            $table->string('organization_id');
            $table->string('initiated_by');
            $table->string('ledger_transaction_id')->nullable();

            $table->bigInteger('amount_minor');
            $table->string('currency');

            // docs/06-wallet-et-grand-livre-wasplex.md §20.2, trimmed to the
            // states this deposit flow can actually reach.
            $table->string('status')->default('created');

            $table->string('provider_code')->default('geniuspay');
            $table->string('provider_reference')->nullable();
            $table->string('checkout_url')->nullable();
            $table->string('idempotency_key')->unique();

            // Provider-safe fields only (reference, status, amount,
            // currency) — never customer PII (docs/chantiers/
            // HOTFIX-P003-GENIUSPAY-SANDBOX.md §4/§8).
            $table->json('metadata')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
            $table->index('provider_reference');
        });

        DB::statement('alter table advertiser_wallet_deposits add constraint advertiser_wallet_deposits_amount_positive check (amount_minor > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('advertiser_wallet_deposits');
    }
};
