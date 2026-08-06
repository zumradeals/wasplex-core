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
        Schema::create('feed_ad_interactions', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // Not a foreign key: cross-module reference by value.
            $table->string('account_id');
            $table->string('campaign_id');

            // like | save | share
            $table->string('type');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['campaign_id', 'type']);
            $table->index(['account_id', 'campaign_id', 'type']);
        });

        DB::statement("alter table feed_ad_interactions add constraint feed_ad_interactions_type_check check (type in ('like', 'save', 'share'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_ad_interactions');
    }
};
