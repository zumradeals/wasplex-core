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
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->string('distribution_mode')->default('members_only')->after('status');
            $table->string('public_slug')->nullable()->unique()->after('distribution_mode');
        });

        DB::statement("alter table campaigns add constraint campaigns_distribution_mode_check check (distribution_mode in ('members_only', 'members_public'))");

        Schema::create('campaign_public_visits', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('campaign_id')->index();
            $table->string('campaign_version_id')->index();
            $table->char('visitor_hash', 64);
            $table->char('visit_hash', 64);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('join_clicked_at')->nullable();
            $table->unsignedInteger('share_count')->default(0);
            $table->timestamps();

            // Le navigateur ne transmet que des jetons aléatoires first-party.
            // Seuls leurs HMAC sont persistés : aucun fingerprint, IP ou jeton
            // brut n'entre dans la mesure de portée publique.
            $table->unique(['campaign_id', 'visit_hash'], 'campaign_public_visits_campaign_visit_unique');
            $table->index(['campaign_id', 'visitor_hash'], 'campaign_public_visits_campaign_visitor_index');
            $table->index(['campaign_id', 'completed_at'], 'campaign_public_visits_campaign_completed_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_public_visits');

        DB::statement('alter table campaigns drop constraint if exists campaigns_distribution_mode_check');

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropUnique(['public_slug']);
            $table->dropColumn(['distribution_mode', 'public_slug']);
        });
    }
};
