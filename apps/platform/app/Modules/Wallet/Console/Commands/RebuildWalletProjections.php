<?php

namespace App\Modules\Wallet\Console\Commands;

use App\Modules\Wallet\Application\Services\WalletProjector;
use App\Modules\Wallet\Infrastructure\Models\Wallet;
use Illuminate\Console\Command;

final class RebuildWalletProjections extends Command
{
    protected $signature = 'wallet:rebuild {wallet? : ULID du Wallet à reconstruire}';

    protected $description = 'Reconstruit les projections Wallet depuis le Grand Livre.';

    public function handle(WalletProjector $projector): int
    {
        $walletId = $this->argument('wallet');
        $query = Wallet::query()->when($walletId, static fn ($builder) => $builder->whereKey($walletId));
        $count = 0;
        $query->orderBy('id')->each(function (Wallet $wallet) use ($projector, &$count): void {
            $projector->rebuild($wallet);
            $count++;
        });

        $this->info("{$count} projection(s) reconstruite(s) depuis le Grand Livre.");

        return self::SUCCESS;
    }
}
