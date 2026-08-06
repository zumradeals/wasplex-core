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
        Schema::create('campaign_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->jsonb('creative_configuration')->nullable();
            $table->jsonb('audience_configuration')->nullable();
            $table->jsonb('budget_configuration')->nullable();
            $table->jsonb('cta_configuration')->nullable();
            $table->foreignUlid('price_version_id')->nullable()->constrained('advertising_price_versions');
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->unique(['campaign_id', 'version_number']);
        });

        DB::statement("alter table campaign_versions add constraint campaign_versions_status_check check (status in ('draft', 'quoted', 'funded', 'submitted'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_versions');
    }
};
