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
        Schema::create('campaign_envelope_consumptions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_quote_id')->constrained('campaign_quotes')->restrictOnDelete();
            $table->string('economic_class');
            $table->string('status')->default('reserved');
            $table->unsignedBigInteger('gain_minor');
            $table->timestamp('reserved_at');
            $table->timestamp('expires_at');
            $table->timestamp('captured_at')->nullable();
            $table->string('idempotency_key')->unique();
            $table->timestamps();

            $table->index(['campaign_quote_id', 'economic_class', 'status']);
        });

        DB::statement("alter table campaign_envelope_consumptions add constraint campaign_envelope_consumptions_status_check check (status in ('reserved', 'captured', 'released', 'expired'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_envelope_consumptions');
    }
};
