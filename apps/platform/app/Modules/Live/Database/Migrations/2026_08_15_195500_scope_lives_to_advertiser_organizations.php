<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lives', function (Blueprint $table): void {
            $table->foreignUlid('advertiser_organization_id')
                ->nullable()
                ->after('owner_account_id')
                ->constrained('organizations')
                ->restrictOnDelete();

            $table->index(
                ['advertiser_organization_id', 'status', 'scheduled_at'],
                'lives_advertiser_status_schedule_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('lives', function (Blueprint $table): void {
            $table->dropIndex('lives_advertiser_status_schedule_idx');
            $table->dropConstrainedForeignId('advertiser_organization_id');
        });
    }
};
