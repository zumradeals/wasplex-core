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
        Schema::create('feed_ad_deliveries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('feed_session_id')->constrained('feed_sessions')->cascadeOnDelete();

            // Not foreign keys: cross-module references by value to
            // Identity's Account and Campaigns' Campaign (docs/CLAUDE.md §6).
            $table->string('account_id');
            $table->string('campaign_id');

            // Captured at reservation time so completion can still post to
            // the Grand Livre even if the campaign is suspended mid-flight
            // (the reservation was valid when made) — not a foreign key.
            $table->string('organization_id');

            // Not a foreign key either: Campaigns owns this row exclusively
            // (docs/chantiers/P009-CHANTIER.md §3) — Feed only keeps the id
            // it received back from CampaignEnvelopeContract::reserveSlot().
            $table->string('campaign_envelope_consumption_id');

            $table->string('economic_class');
            $table->unsignedBigInteger('gain_minor');
            $table->unsignedBigInteger('required_duration_ms');
            $table->unsignedBigInteger('visible_duration_ms')->default(0);
            $table->unsignedTinyInteger('progress_percent')->default(0);

            // reserved -> started -> completed | abandoned | expired
            $table->string('status')->default('reserved');

            $table->timestamp('reserved_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('ledger_transaction_id')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'campaign_id', 'created_at']);
        });

        DB::statement("alter table feed_ad_deliveries add constraint feed_ad_deliveries_status_check check (status in ('reserved', 'started', 'completed', 'abandoned', 'expired'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_ad_deliveries');
    }
};
