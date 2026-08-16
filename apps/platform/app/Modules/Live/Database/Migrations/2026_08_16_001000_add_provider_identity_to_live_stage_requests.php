<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_stage_requests', function (Blueprint $table): void {
            $table->string('provider_participant_identity', 190)
                ->nullable()
                ->after('account_id');
            $table->index('provider_participant_identity');
        });
    }

    public function down(): void
    {
        Schema::table('live_stage_requests', function (Blueprint $table): void {
            $table->dropIndex(['provider_participant_identity']);
            $table->dropColumn('provider_participant_identity');
        });
    }
};
