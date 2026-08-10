<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertising_price_versions', function (Blueprint $table): void {
            $table->unsignedBigInteger('minimum_budget_minor')->default(5000);
            $table->unsignedInteger('duration_days')->default(7);
        });
    }

    public function down(): void
    {
        Schema::table('advertising_price_versions', function (Blueprint $table): void {
            $table->dropColumn(['minimum_budget_minor', 'duration_days']);
        });
    }
};
