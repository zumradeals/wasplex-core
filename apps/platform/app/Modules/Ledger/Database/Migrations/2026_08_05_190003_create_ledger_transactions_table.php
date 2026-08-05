<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_transactions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('journal_id')->constrained('ledger_journals')->restrictOnDelete();
            $table->string('type');

            // pending_approval: only reachable via the correction propose/
            // approve workflow (docs/chantiers/P002-CHANTIER.md capacités
            // wallet.correction.propose/approve) — never published, freely
            // replaced by the trigger added in a later migration. posted:
            // append-only forever. rejected: a proposal that was declined,
            // equally append-only (never became economically real, but the
            // decision itself is kept for audit).
            $table->string('status')->default('posted');

            $table->string('source_module');
            $table->string('business_reference')->nullable();
            $table->string('currency');
            $table->string('rule_version')->default('v1');

            // Not a foreign key: references an Identity Account by value
            // (docs/CLAUDE.md #6 — no cross-module DB constraint).
            $table->string('created_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['status', 'type']);
            $table->index('business_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_transactions');
    }
};
