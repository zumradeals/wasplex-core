<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Console;

use App\Modules\Campaigns\Infrastructure\Models\AdvertisingPriceCatalog;
use App\Modules\Campaigns\Infrastructure\Models\AdvertisingPriceVersion;
use Illuminate\Console\Command;

/**
 * Docs/05-modele-economique-publicitaire-wasplex.md §9 gives a conceptual
 * pricing formula (base × format × durée × précision × territoire ×
 * rareté × classe × volume) but no concrete FCFA base price anywhere in
 * the corpus. Inventing one would be a silent product decision
 * (CLAUDE.md §2) — same discipline as P004's subscription plans.
 *
 * Seeded `draft` at base price 0: an admin must set the real base price
 * and publish via PATCH/POST /api/admin/advertising/pricing before any
 * campaign can be quoted. Only the class-targeting coefficient (already
 * published by Subscriptions, P004) and the format multiplier are real
 * pricing axes in this chantier — duration/territory/rarity/volume stay
 * neutral (1.0) until the founder decides real values (docs/chantiers/
 * P006-CHANTIER.md §3.2).
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
                'status' => AdvertisingPriceVersion::STATUS_DRAFT,
                'currency' => 'XOF',
                'base_price_minor_per_event' => 0,
                'image_multiplier' => '1.0000',
                'video_multiplier' => '1.0000',
            ]);
        }

        $this->info('Catalogue de prix publicitaire "standard" initialisé.');
        $this->warn('Reste en brouillon (prix de base à 0) — voir docs/chantiers/P006-RAPPORT.md.');

        return self::SUCCESS;
    }
}
