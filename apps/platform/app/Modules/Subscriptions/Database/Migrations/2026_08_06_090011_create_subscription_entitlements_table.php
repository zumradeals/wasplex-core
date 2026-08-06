<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_entitlements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_subscription_id')->constrained('user_subscriptions')->cascadeOnDelete();

            // docs/03 §14: "chaque plan peut définir accès Fonds, plafonds
            // Fonds, Carte Wasplex, Live, statistiques..." — stored as data
            // here, not enforced anywhere yet (the modules that would read
            // this, Fonds/Carte/Live, don't exist). §14 also states the
            // Gratuit plan is never eligible to Fonds; enforced at seed
            // time by the catalog command, not hardcoded here.
            $table->string('key');
            $table->boolean('enabled')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_subscription_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_entitlements');
    }
};
