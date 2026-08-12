<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $unitMs = max(1, (int) config('campaigns.attention_unit_ms'));
        $versions = DB::table('campaign_versions')->select(['id', 'creative_configuration'])->whereNotNull('creative_configuration')->get();

        foreach ($versions as $version) {
            $creative = json_decode((string) $version->creative_configuration, true);
            if (! is_array($creative) || empty($creative['asset_id'])) {
                continue;
            }

            $asset = DB::table('creative_assets')->where('id', $creative['asset_id'])->first();
            if ($asset === null || $asset->type !== 'video') {
                continue;
            }

            $durationMs = (int) ($asset->duration_ms ?? 0);
            if ($durationMs <= 0 && $asset->duration !== null) {
                $durationMs = ((int) $asset->duration) * 1000;
            }
            if ($durationMs <= 0) {
                continue;
            }

            $creative['format'] = 'video';
            $creative['duration_ms'] = $durationMs;
            $creative['attention_units'] = (int) ceil($durationMs / $unitMs);
            unset($creative['duration_seconds']);

            DB::table('campaign_versions')->where('id', $version->id)->update([
                'creative_configuration' => json_encode($creative),
            ]);
        }
    }

    public function down(): void
    {
        // Aucun historique financier n'est réécrit en retour arrière.
    }
};
