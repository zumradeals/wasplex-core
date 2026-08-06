<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only : chaque décision de modération est une nouvelle
        // ligne, jamais une mise à jour, pour garder l'historique complet
        // (docs/13 §78 "modérer les médias").
        Schema::create('creative_moderation_cases', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('creative_asset_id')->constrained('creative_assets')->cascadeOnDelete();

            // approve | request_changes | reject (miroir de §55, appliqué aux médias).
            $table->string('decision');
            $table->text('reason')->nullable();

            // Not a foreign key: cross-module reference by value to
            // Identity's Account (l'admin qui a décidé).
            $table->string('decided_by');
            $table->timestamp('decided_at')->useCurrent();

            $table->index(['creative_asset_id', 'decided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_moderation_cases');
    }
};
