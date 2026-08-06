<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only webhook delivery log (docs/19-integrations-externes-wasplex.md
 * §20-23: "un webhook doit être authentifié, signé, horodaté, idempotent,
 * journalisé, rejouable"). Never stores the raw payload verbatim — only the
 * safe fields needed to prove what was received and decide idempotently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertiser_wallet_deposit_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('deposit_id')->nullable()->constrained('advertiser_wallet_deposits')->nullOnDelete();

            $table->string('provider_code');
            $table->string('event_type')->nullable();
            $table->string('provider_status_raw')->nullable();
            $table->boolean('signature_valid');

            // received, verified, rejected, processed, duplicated, failed
            // (docs/19 §22, trimmed — requeued/dead_lettered are out of
            // scope without an outbox/queue, excluded from P003).
            $table->string('status');

            $table->timestamp('created_at')->useCurrent();

            $table->index(['provider_code', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertiser_wallet_deposit_events');
    }
};
