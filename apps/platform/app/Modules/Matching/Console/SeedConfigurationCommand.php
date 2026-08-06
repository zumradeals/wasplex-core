<?php

declare(strict_types=1);

namespace App\Modules\Matching\Console;

use App\Modules\Matching\Infrastructure\Models\MatchingConfiguration;
use Illuminate\Console\Command;

final class SeedConfigurationCommand extends Command
{
    protected $signature = 'matching:seed-configuration';

    protected $description = 'Initialise une configuration de Matching par défaut, en brouillon (idempotent).';

    public function handle(): int
    {
        $hasAny = MatchingConfiguration::query()->exists();

        if (! $hasAny) {
            MatchingConfiguration::query()->create([
                'status' => MatchingConfiguration::STATUS_DRAFT,
                'frequency_window_hours' => 24,
                'frequency_max_per_window' => 3,
                'fatigue_threshold' => 10,
            ]);
        }

        $this->info('Configuration de Matching initialisée (brouillon).');
        $this->warn("Seuils non appliqués tant qu'aucun compteur de livraison réelle n'existe (P009).");

        return self::SUCCESS;
    }
}
