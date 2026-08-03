<?php

namespace App\Modules\Wallet\Console\Commands;

use App\Modules\Wallet\Application\Contracts\WalletReservationContract;
use Illuminate\Console\Command;

final class ExpireWalletReservations extends Command
{
    protected $signature = 'wallet:expire-reservations';

    protected $description = 'Libère de manière idempotente les réservations Wallet expirées.';

    public function handle(WalletReservationContract $reservations): int
    {
        $count = $reservations->expireDue();
        $this->info("{$count} réservation(s) expirée(s) libérée(s).");

        return self::SUCCESS;
    }
}
