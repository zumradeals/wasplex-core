<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // Stable commercial identity (docs/03 §2: "Premium mensuel,
            // Premium annuel" are both plans, both mapped to the PREMIUM
            // class). Publish/suspend state lives on the version, not here.
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
