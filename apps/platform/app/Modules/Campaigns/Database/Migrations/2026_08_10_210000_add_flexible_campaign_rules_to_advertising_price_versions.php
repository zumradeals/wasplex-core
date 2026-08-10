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
        Schema::table('advertising_price_versions', function (Blueprint $table): void {
            $table->unsignedBigInteger('minimum_daily_budget_minor')->default(500);
            $table->unsignedInteger('minimum_duration_days')->default(1);
            $table->unsignedInteger('maximum_duration_days')->default(30);
        });

        DB::table('advertising_price_versions')
            ->whereIn('status', ['draft', 'published'])
            ->update([
                'minimum_budget_minor' => 2000,
                'minimum_daily_budget_minor' => 500,
                'minimum_duration_days' => 1,
                'maximum_duration_days' => 30,
                'duration_days' => 7,
            ]);
    }

    public function down(): void
    {
        Schema::table('advertising_price_versions', function (Blueprint $table): void {
            $table->dropColumn([
                'minimum_daily_budget_minor',
                'minimum_duration_days',
                'maximum_duration_days',
            ]);
        });
    }
};
