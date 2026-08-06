<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('economic_classes', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // docs/03-abonnements-et-classes-economiques-wasplex.md §2:
            // FREE/PREMIUM/GOLD/PLATINUM — stable technical codes even if
            // public names change.
            $table->string('code')->unique();
            $table->string('status')->default('active');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('economic_classes');
    }
};
