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
        Schema::create('campaign_quotes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_version_id')->constrained('campaign_versions')->cascadeOnDelete();
            $table->string('currency', 3);
            $table->unsignedBigInteger('gross_amount_minor');
            $table->unsignedBigInteger('net_distributable_amount_minor');
            $table->unsignedInteger('estimated_events');
            $table->unsignedInteger('estimated_reach_min');
            $table->unsignedInteger('estimated_reach_max');
            $table->jsonb('class_breakdown');
            $table->timestamp('expires_at');
            $table->foreignUlid('price_version_id')->constrained('advertising_price_versions');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        DB::statement("alter table campaign_quotes add constraint campaign_quotes_status_check check (status in ('active', 'expired', 'consumed'))");
        DB::statement('alter table campaign_quotes add constraint campaign_quotes_net_le_gross check (net_distributable_amount_minor <= gross_amount_minor)');
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_quotes');
    }
};
