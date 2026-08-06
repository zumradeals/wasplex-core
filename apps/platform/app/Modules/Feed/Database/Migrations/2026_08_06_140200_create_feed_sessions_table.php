<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // Not a foreign key: cross-module reference by value to
            // Identity's Account (docs/CLAUDE.md §6).
            $table->string('account_id');

            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();

            $table->index(['account_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_sessions');
    }
};
