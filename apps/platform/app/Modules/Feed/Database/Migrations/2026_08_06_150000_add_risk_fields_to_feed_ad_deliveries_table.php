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
        Schema::table('feed_ad_deliveries', function (Blueprint $table): void {
            // docs/07 §17 : champ recommandé pour la session d'attention,
            // absent de P009 — nécessaire pour détecter un rythme de
            // heartbeat anormal (docs/chantiers/P010-CHANTIER.md §3).
            // Précision milliseconde : une preuve renforcée fondée sur des
            // écarts de quelques centaines de millisecondes est sans objet
            // sur une colonne tronquée à la seconde.
            $table->timestamp('last_heartbeat_at', 3)->nullable()->after('visible_duration_ms');
            $table->unsignedInteger('risk_signal_count')->default(0)->after('last_heartbeat_at');
            $table->string('last_risk_signal_code')->nullable()->after('risk_signal_count');
        });

        // started_at (P009) partage la même faiblesse : tronqué à la
        // seconde, il rend le calcul du temps réel écoulé
        // (AttentionService::heartbeat()) imprécis à moins d'une seconde
        // près — une marge trop large pour une antifraude honnête.
        DB::statement('alter table feed_ad_deliveries alter column started_at type timestamp(3)');

        DB::statement('alter table feed_ad_deliveries drop constraint feed_ad_deliveries_status_check');
        DB::statement("alter table feed_ad_deliveries add constraint feed_ad_deliveries_status_check check (status in ('reserved', 'started', 'completed', 'abandoned', 'expired', 'held', 'rejected'))");
    }

    public function down(): void
    {
        DB::statement('alter table feed_ad_deliveries drop constraint feed_ad_deliveries_status_check');
        DB::statement("alter table feed_ad_deliveries add constraint feed_ad_deliveries_status_check check (status in ('reserved', 'started', 'completed', 'abandoned', 'expired'))");

        DB::statement('alter table feed_ad_deliveries alter column started_at type timestamp(0)');

        Schema::table('feed_ad_deliveries', function (Blueprint $table): void {
            $table->dropColumn(['last_heartbeat_at', 'risk_signal_count', 'last_risk_signal_code']);
        });
    }
};
