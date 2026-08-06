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
        Schema::create('subscription_payments', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // Not a foreign key: cross-module reference by value to
            // Identity's Account.
            $table->string('account_id');

            $table->foreignUlid('plan_version_id')->constrained('subscription_plan_versions')->restrictOnDelete();

            // Set only once the payment is confirmed and the subscription
            // activated/renewed/upgraded — null while awaiting payment.
            $table->foreignUlid('user_subscription_id')->nullable()->constrained('user_subscriptions')->nullOnDelete();

            $table->bigInteger('amount_minor');
            $table->string('currency');

            // created -> awaiting_payment -> confirmed -> activated
            //                              -> rejected / expired
            // Same lifecycle discipline as advertiser_wallet_deposits (P003).
            $table->string('status')->default('created');

            $table->string('provider_code')->default('geniuspay');
            $table->string('provider_reference')->nullable();
            $table->string('checkout_url')->nullable();
            $table->string('idempotency_key')->unique();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['account_id', 'created_at']);
            $table->index('provider_reference');
        });

        DB::statement('alter table subscription_payments add constraint subscription_payments_amount_non_negative check (amount_minor >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
