<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geniuspay_configurations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('environment')->default('sandbox');
            $table->text('api_key');
            $table->text('api_secret');
            $table->text('webhook_secret');
            $table->string('updated_by');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geniuspay_configurations');
    }
};
