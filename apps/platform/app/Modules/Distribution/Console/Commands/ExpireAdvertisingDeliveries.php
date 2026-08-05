<?php

namespace App\Modules\Distribution\Console\Commands;

use App\Modules\Distribution\Application\Services\AdvertisingDeliveryService;
use Illuminate\Console\Command;

final class ExpireAdvertisingDeliveries extends Command
{
    protected $signature = 'distribution:expire-deliveries';

    protected $description = 'Expire les baux publicitaires non livrés sans consommer de quota.';

    public function handle(AdvertisingDeliveryService $deliveries): int
    {
        $count = $deliveries->expireDue();
        $this->info("Baux de livraison expirés : {$count}");

        return self::SUCCESS;
    }
}
