<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_provider_configurations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('provider', 32);
            $table->string('environment', 16);
            $table->text('api_key')->nullable();
            $table->text('api_secret')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->string('base_url', 255);
            $table->json('checkout_hosts');
            $table->boolean('is_active')->default(false);
            $table->string('last_test_status', 24)->nullable();
            $table->string('last_test_message', 500)->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->ulid('updated_by_account_id')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'environment'], 'payment_provider_environment_unique');
            $table->index(['provider', 'is_active']);
            $table->foreign('updated_by_account_id')->references('id')->on('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_provider_configurations');
    }
};
