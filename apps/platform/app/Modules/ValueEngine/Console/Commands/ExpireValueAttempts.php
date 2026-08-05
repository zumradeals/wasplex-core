<?php

namespace App\Modules\ValueEngine\Console\Commands;

use App\Modules\ValueEngine\Application\Services\AdvertisingValueEngine;
use Illuminate\Console\Command;

final class ExpireValueAttempts extends Command
{
    protected $signature = 'value-engine:expire-attempts';

    protected $description = 'Libère les tentatives publicitaires expirées sans créer de valeur.';

    public function handle(AdvertisingValueEngine $engine): int
    {
        $expired = $engine->expireDue();
        $this->info($expired.' tentative(s) publicitaire(s) expirée(s).');

        return self::SUCCESS;
    }
}
