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
        Schema::create('advertising_price_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('catalog_id')->constrained('advertising_price_catalogs')->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->string('currency', 3);
            $table->unsignedBigInteger('base_price_minor_per_event')->default(0);
            $table->decimal('image_multiplier', 6, 4)->default(1.0);
            $table->decimal('video_multiplier', 6, 4)->default(1.0);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        DB::statement('alter table advertising_price_versions add constraint advertising_price_versions_status_check check (status in (\'draft\', \'published\', \'suspended\'))');
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising_price_versions');
    }
};
