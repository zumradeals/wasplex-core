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
        Schema::table('feed_ad_deliveries', function (Blueprint $table): void {
            $table->unsignedSmallInteger('quota_units')->default(1)->after('required_duration_ms');
            $table->boolean('requires_media_end')->default(false)->after('quota_units');
            $table->timestamp('media_ended_at', 3)->nullable()->after('requires_media_end');
        });

        DB::table('feed_ad_deliveries')->where('is_replay', true)->update(['quota_units' => 0]);
    }

    public function down(): void
    {
        Schema::table('feed_ad_deliveries', function (Blueprint $table): void {
            $table->dropColumn(['quota_units', 'requires_media_end', 'media_ended_at']);
        });
    }
};
