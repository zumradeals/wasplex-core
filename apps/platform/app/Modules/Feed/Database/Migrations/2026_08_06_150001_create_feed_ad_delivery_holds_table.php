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
        Schema::create('feed_ad_delivery_holds', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('feed_ad_delivery_id')->constrained('feed_ad_deliveries')->cascadeOnDelete();

            $table->unsignedBigInteger('amount_minor');
            $table->string('reason_code');

            // docs/16 §24, sous-ensemble pertinent sans retrait
            // (docs/chantiers/P010-CHANTIER.md §2.9) : created -> under_review
            // -> released|rejected|expired.
            $table->string('status')->default('created');

            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by')->nullable();
            $table->string('resolution_note')->nullable();
            $table->jsonb('evidence')->default('{}');
            $table->timestamps();

            $table->index(['status', 'opened_at']);
        });

        DB::statement("alter table feed_ad_delivery_holds add constraint feed_ad_delivery_holds_status_check check (status in ('created', 'under_review', 'released', 'rejected', 'expired'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_ad_delivery_holds');
    }
};
