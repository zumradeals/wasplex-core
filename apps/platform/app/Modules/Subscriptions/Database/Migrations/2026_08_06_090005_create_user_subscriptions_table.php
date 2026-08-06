<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_subscriptions', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // Not a foreign key: cross-module reference by value to
            // Identity's Account (docs/CLAUDE.md §6).
            $table->string('account_id');

            $table->foreignUlid('plan_version_id')->constrained('subscription_plan_versions')->restrictOnDelete();
            $table->foreignUlid('economic_class_id')->constrained('economic_classes')->restrictOnDelete();

            // docs/03 §18: downgrade applies at next cycle, never
            // retroactively — the target plan is scheduled, not applied.
            $table->foreignUlid('scheduled_plan_version_id')->nullable()->constrained('subscription_plan_versions')->nullOnDelete();

            // pending_payment | active | scheduled_downgrade | expired | cancelled
            $table->string('status')->default('pending_payment');

            // Null while pending_payment: "started" means activated, not
            // merely selected (docs/03 §15: choix du plan précède le
            // paiement et l'activation).
            $table->timestamp('started_at')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};
