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
        Schema::create('subscription_quota_counters', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('subscription_cycle_id')->constrained('subscription_cycles')->cascadeOnDelete();

            // docs/03 §3-§4: quota alloué au début du cycle (snapshot de la
            // classe au moment de l'initialisation), et unités
            // effectivement consommées par AdDelivered — jamais par
            // QualifiedAttention (§4: "deux événements doivent rester
            // distincts").
            $table->unsignedInteger('quota_allocated');
            $table->unsignedInteger('quota_consumed')->default(0);
            $table->timestamps();

            $table->unique('subscription_cycle_id');
        });

        DB::statement('alter table subscription_quota_counters add constraint subscription_quota_counters_not_over_allocated check (quota_consumed <= quota_allocated)');
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_quota_counters');
    }
};
