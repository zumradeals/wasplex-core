<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // docs/13 §14/§17 — la charte est mise à jour en place (pas
        // historisée en V1, docs/chantiers/P005-CHANTIER.md §3.2).
        Schema::create('brand_guidelines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('brand_id')->constrained('brands')->cascadeOnDelete();

            // professionnel/chaleureux/direct/premium/jeune/institutionnel/
            // éducatif/promotionnel, ou une valeur libre (§17).
            $table->string('tone')->nullable();
            $table->text('custom_instructions')->nullable();
            $table->text('mandatory_mentions')->nullable();
            $table->text('forbidden_mentions')->nullable();
            $table->text('usage_rules')->nullable();

            $table->timestamps();

            $table->unique('brand_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_guidelines');
    }
};
