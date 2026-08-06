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
        Schema::create('matching_decisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // Not a foreign key: cross-module reference by value to
            // Campaigns' Campaign/CampaignVersion (docs/CLAUDE.md §6).
            $table->string('campaign_id');
            $table->string('campaign_version_id');

            // Not a foreign key: cross-module reference by value to
            // Identity's Account.
            $table->string('account_id');

            $table->foreignUlid('matching_configuration_id')->nullable()->constrained('matching_configurations')->nullOnDelete();

            $table->string('decision');
            $table->jsonb('reason_codes')->default('[]');
            $table->timestamp('decided_at');
            $table->timestamps();

            // Recalculée (upsert) à chaque évaluation — ce n'est pas une
            // écriture financière, docs/chantiers/P008-CHANTIER.md §3.9.
            $table->unique(['campaign_id', 'account_id']);
        });

        DB::statement("alter table matching_decisions add constraint matching_decisions_decision_check check (decision in ('eligible', 'ineligible', 'withheld'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('matching_decisions');
    }
};
