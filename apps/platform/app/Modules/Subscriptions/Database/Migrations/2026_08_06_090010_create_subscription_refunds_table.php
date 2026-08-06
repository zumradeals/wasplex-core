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
        Schema::create('subscription_refunds', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('subscription_payment_id')->constrained('subscription_payments')->restrictOnDelete();

            $table->bigInteger('amount_minor');
            $table->string('currency');
            $table->string('reason');

            // requested -> completed / rejected
            $table->string('status')->default('requested');

            $table->string('requested_by');
            $table->timestamps();
        });

        DB::statement('alter table subscription_refunds add constraint subscription_refunds_amount_positive check (amount_minor > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_refunds');
    }
};
