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
        Schema::table('creative_assets', function (Blueprint $table): void {
            $table->unsignedInteger('duration_ms')->nullable()->after('duration');
        });

        DB::table('creative_assets')
            ->where('type', 'video')
            ->whereNull('duration_ms')
            ->whereNotNull('duration')
            ->update(['duration_ms' => DB::raw('duration * 1000')]);
    }

    public function down(): void
    {
        Schema::table('creative_assets', function (Blueprint $table): void {
            $table->dropColumn('duration_ms');
        });
    }
};
