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
        Schema::create('campaign_review_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_review_case_id')->constrained('campaign_review_cases')->cascadeOnDelete();
            $table->string('event_type');
            $table->string('actor_account_id')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at');
        });

        DB::statement("alter table campaign_review_events add constraint campaign_review_events_type_check check (event_type in ('submitted', 'changes_requested', 'resubmitted', 'approved', 'rejected', 'suspended'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_review_events');
    }
};
