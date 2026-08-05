<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertiser_wallets', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // Not a foreign key: references an Identity Organization by
            // value (docs/CLAUDE.md #6 — no cross-module DB constraint).
            $table->string('organization_id')->unique();

            $table->string('currency')->default('WP');
            $table->string('status')->default('active');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertiser_wallets');
    }
};
