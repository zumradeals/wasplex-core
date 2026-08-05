<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code');
            $table->foreignUlid('account_type_id')->constrained('ledger_account_types')->restrictOnDelete();

            // Not foreign keys: owner_type/owner_id reference another module's
            // entity (e.g. an Identity Account) by value, never by DB
            // constraint, per docs/CLAUDE.md #6 (module data ownership).
            // 'system' owner_type is used for Wasplex-owned accounts with a
            // fixed owner_id ('wasplex'), so the uniqueness constraint below
            // never relies on NULL-distinctness semantics.
            $table->string('owner_type');
            $table->string('owner_id');

            $table->string('currency');
            $table->string('country_code', 2)->nullable();
            $table->string('status')->default('active');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['code', 'owner_type', 'owner_id']);
            $table->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_accounts');
    }
};
