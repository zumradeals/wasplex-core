<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // docs/13 §16.
        Schema::create('brand_typographies', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('brand_id')->constrained('brands')->cascadeOnDelete();

            // principale/secondaire/remplacement.
            $table->string('role');
            $table->string('family');
            $table->text('usages')->nullable();
            $table->string('recommended_sizes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_typographies');
    }
};
