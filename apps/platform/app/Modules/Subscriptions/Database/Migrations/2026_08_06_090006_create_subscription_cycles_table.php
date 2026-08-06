<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_cycles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_subscription_id')->constrained('user_subscriptions')->cascadeOnDelete();

            // docs/03 §8: cycle mensuel civil, timezone du compte, aucun
            // report du quota inutilisé.
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->timestamp('reset_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_subscription_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_cycles');
    }
};
