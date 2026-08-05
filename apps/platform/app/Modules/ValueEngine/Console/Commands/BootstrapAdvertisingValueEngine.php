<?php

namespace App\Modules\ValueEngine\Console\Commands;

use App\Modules\ValueEngine\Application\Services\ValueEngineCatalog;
use Illuminate\Console\Command;

final class BootstrapAdvertisingValueEngine extends Command
{
    protected $signature = 'value-engine:bootstrap-advertising';

    protected $description = 'Initialise le Super Moteur publicitaire et sa première règle de valeur.';

    public function handle(ValueEngineCatalog $catalog): int
    {
        $catalog->ensureAdvertisingCore();

        $this->info('Super Moteur publicitaire prêt : événement, règle et compte de produit installés.');

        return self::SUCCESS;
    }
}
