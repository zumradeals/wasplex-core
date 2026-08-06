<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_ad_comments', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // Not a foreign key: cross-module reference by value.
            $table->string('account_id');
            $table->string('campaign_id');

            $table->string('body', 500);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['campaign_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_ad_comments');
    }
};
