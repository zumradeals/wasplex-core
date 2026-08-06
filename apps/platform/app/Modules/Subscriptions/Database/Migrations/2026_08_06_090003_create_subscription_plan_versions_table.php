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
        Schema::create('subscription_plan_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('plan_id')->constrained('subscription_plans')->restrictOnDelete();

            // docs/03 §21: "création et versionnement des plans... dates
            // d'effet... suspension". draft -> published -> suspended.
            $table->string('status')->default('draft');

            $table->bigInteger('price_minor');
            $table->string('currency');
            $table->unsignedInteger('duration_days');

            // Snapshot of the included services (§14/§20) at this version —
            // never re-derived later, so a published plan's advertised
            // benefits never silently drift.
            $table->json('services_snapshot')->nullable();

            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['plan_id', 'status']);
        });

        DB::statement('alter table subscription_plan_versions add constraint subscription_plan_versions_price_non_negative check (price_minor >= 0)');
        DB::statement('alter table subscription_plan_versions add constraint subscription_plan_versions_duration_positive check (duration_days > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_versions');
    }
};
