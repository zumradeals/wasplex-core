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
        Schema::create('campaign_review_cases', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignUlid('campaign_version_id')->constrained('campaign_versions');
            $table->string('status')->default('open');
            $table->string('decision')->nullable();
            $table->text('reason')->nullable();
            $table->string('requested_changes_by')->nullable();
            $table->string('decided_by')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
        });

        DB::statement("alter table campaign_review_cases add constraint campaign_review_cases_status_check check (status in ('open', 'decided'))");
        DB::statement("alter table campaign_review_cases add constraint campaign_review_cases_decision_check check (decision is null or decision in ('approved', 'changes_requested', 'rejected'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_review_cases');
    }
};
