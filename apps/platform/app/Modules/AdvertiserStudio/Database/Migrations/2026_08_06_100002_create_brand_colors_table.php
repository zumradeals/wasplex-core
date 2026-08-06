<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // docs/13 §15 — champs exacts.
        Schema::create('brand_colors', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('brand_id')->constrained('brands')->cascadeOnDelete();

            $table->string('name');
            $table->string('hex', 7);
            $table->string('rgb')->nullable();
            $table->string('cmyk')->nullable();

            // principale/secondaire/accent/fond/texte (§15).
            $table->string('usage');
            $table->unsignedSmallInteger('priority')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_colors');
    }
};
