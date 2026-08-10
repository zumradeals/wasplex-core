<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Console;

use App\Modules\Campaigns\Infrastructure\Models\AdvertisingPriceCatalog;
use App\Modules\Campaigns\Infrastructure\Models\AdvertisingPriceVersion;
use Illuminate\Console\Command;

/**
 * Configuration de départ volontairement simple et administrable : budget
 * minimum de 2 000 FCFA, 500 FCFA par jour et diffusion libre de 1 à 30
 * jours (7 jours proposés). Aucun multiplicateur média, poids ou coefficient.
 */
final class SeedPriceCatalogCommand extends Command
{
    protected $signature = 'campaigns:seed-price-catalog';

    protected $description = 'Initialise le catalogue de prix publicitaire standard (idempotent).';

    public function handle(): int
    {
        $catalog = AdvertisingPriceCatalog::query()->firstOrCreate(['code' => 'standard']);

        $hasVersion = AdvertisingPriceVersion::query()->where('catalog_id', $catalog->id)->exists();

        if (! $hasVersion) {
            AdvertisingPriceVersion::query()->create([
                'catalog_id' => $catalog->id,
                'status' => AdvertisingPriceVersion::STATUS_PUBLISHED,
                'currency' => 'XOF',
                'base_price_minor_per_event' => 0,
                'image_multiplier' => '1.0000',
                'video_multiplier' => '1.0000',
                'minimum_budget_minor' => 2000,
                'minimum_daily_budget_minor' => 500,
                'minimum_duration_days' => 1,
                'maximum_duration_days' => 30,
                'duration_days' => 7,
                'effective_from' => now(),
                'published_at' => now(),
            ]);
        }

        $this->info('Catalogue de prix publicitaire "standard" initialisé.');
        $this->info('La configuration standard est publiée automatiquement pour ne pas bloquer les premières campagnes.');

        return self::SUCCESS;
    }
}
