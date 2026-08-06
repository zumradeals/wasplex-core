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
        Schema::create('consent_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_consent_id')->constrained('user_consents')->cascadeOnDelete();

            // Not a foreign key: cross-module reference by value to
            // Identity's Account (docs/CLAUDE.md §6).
            $table->string('account_id');

            $table->foreignUlid('consent_purpose_id')->constrained('consent_purposes')->restrictOnDelete();
            $table->foreignUlid('consent_purpose_version_id')->constrained('consent_purpose_versions')->restrictOnDelete();
            $table->string('event_type');
            $table->timestamp('occurred_at');

            // Append-only: no updated_at (docs/CLAUDE.md §9 discipline,
            // same as campaign_review_events in P007).
            $table->timestamp('created_at')->nullable();
        });

        DB::statement("alter table consent_events add constraint consent_events_type_check check (event_type in ('granted', 'denied', 'withdrawn', 'expired', 'superseded'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_events');
    }
};
