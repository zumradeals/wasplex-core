<?php

namespace App\Modules\Wallet\Console\Commands;

use App\Modules\Identity\Application\Services\CapabilityService;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use App\Modules\Wallet\Application\Services\WalletCatalog;
use Illuminate\Console\Command;

final class BootstrapWallet extends Command
{
    protected $signature = 'wallet:bootstrap-core';

    protected $description = 'Initialise le catalogue Wallet P003 et les Wallets des espaces existants.';

    public function handle(WalletCatalog $catalog, CapabilityService $capabilities): int
    {
        $catalog->ensureCore();
        $count = 0;
        UserSpace::query()
            ->whereIn('kind', [SpaceKind::User->value, SpaceKind::Advertiser->value])
            ->orderBy('id')
            ->each(function (UserSpace $space) use ($catalog, $capabilities, &$count): void {
                $catalog->forSpace($space);
                $names = $space->kind === SpaceKind::Advertiser
                    ? ['advertiser.wallet.view', 'advertiser.wallet.fund']
                    : ['wallet.view.self', 'wallet.deposit.create.self', 'wallet.history.view.self'];
                foreach ($names as $name) {
                    $exists = $space->account->capabilityGrants()
                        ->where('space_id', $space->id)
                        ->where('capability', $name)
                        ->whereNull('revoked_at')
                        ->exists();
                    if (! $exists) {
                        $capabilities->grant(
                            account: $space->account,
                            capability: $name,
                            space: $space,
                            organizationId: $space->organization_id,
                            reason: 'Initialisation P003 du Wallet de l’espace',
                        );
                    }
                }
                $count++;
            });

        $this->info("Catalogue P003 prêt et {$count} Wallet(s) vérifié(s).");
        $this->warn('GeniusPay reste verrouillé sur la sandbox ; aucune clé live n’est acceptée.');

        return self::SUCCESS;
    }
}
