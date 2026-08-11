<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_campaign_rewards', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('account_id');
            $table->string('campaign_id');
            $table->string('first_delivery_id');
            $table->string('organization_id');
            $table->string('economic_class');
            $table->unsignedBigInteger('reward_minor');
            $table->unsignedBigInteger('required_duration_ms');
            $table->string('ledger_transaction_id')->nullable();
            $table->timestamp('rewarded_at');
            $table->timestamps();

            // Dernière barrière économique : une campagne ne peut créer
            // qu'un seul droit à récompense pour un même membre, même si
            // plusieurs requêtes arrivent en concurrence.
            $table->unique(['account_id', 'campaign_id'], 'feed_campaign_rewards_account_campaign_unique');
            $table->index('campaign_id');
            $table->index('rewarded_at');
        });

        Schema::table('feed_ad_deliveries', function (Blueprint $table): void {
            $table->boolean('is_replay')->default(false)->after('economic_class');
        });

        DB::statement('alter table feed_ad_deliveries drop constraint feed_ad_deliveries_status_check');
        DB::statement("alter table feed_ad_deliveries add constraint feed_ad_deliveries_status_check check (status in ('reserved', 'started', 'completed', 'abandoned', 'expired', 'held', 'rejected', 'replay'))");

        // Historique : si le bug a déjà crédité plusieurs fois le même couple
        // membre + campagne, seule la première livraison payée devient la
        // preuve de récompense. Aucune écriture du Grand Livre n'est effacée.
        $seen = [];
        $now = now();

        $deliveries = DB::table('feed_ad_deliveries')
            ->where('status', 'completed')
            ->whereNotNull('ledger_transaction_id')
            ->orderBy('completed_at')
            ->orderBy('id')
            ->get([
                'id',
                'account_id',
                'campaign_id',
                'organization_id',
                'economic_class',
                'gain_minor',
                'required_duration_ms',
                'ledger_transaction_id',
                'completed_at',
            ]);

        foreach ($deliveries as $delivery) {
            $key = $delivery->account_id."\0".$delivery->campaign_id;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            DB::table('feed_campaign_rewards')->insert([
                'id' => (string) Str::ulid(),
                'account_id' => $delivery->account_id,
                'campaign_id' => $delivery->campaign_id,
                'first_delivery_id' => $delivery->id,
                'organization_id' => $delivery->organization_id,
                'economic_class' => $delivery->economic_class,
                'reward_minor' => $delivery->gain_minor,
                'required_duration_ms' => $delivery->required_duration_ms,
                'ledger_transaction_id' => $delivery->ledger_transaction_id,
                'rewarded_at' => $delivery->completed_at ?? $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::statement('alter table feed_ad_deliveries drop constraint feed_ad_deliveries_status_check');
        DB::statement("alter table feed_ad_deliveries add constraint feed_ad_deliveries_status_check check (status in ('reserved', 'started', 'completed', 'abandoned', 'expired', 'held', 'rejected'))");

        Schema::table('feed_ad_deliveries', function (Blueprint $table): void {
            $table->dropColumn('is_replay');
        });

        Schema::dropIfExists('feed_campaign_rewards');
    }
};
