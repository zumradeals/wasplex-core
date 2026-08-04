<?php

namespace App\Modules\Wallet\Console\Commands;

use App\Modules\Identity\Application\Services\CapabilityService;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Console\Command;

final class BootstrapGeniusPayConfiguration extends Command
{
    protected $signature = 'geniuspay:bootstrap-configuration';

    protected $description = 'Attribue les capacités de gestion GeniusPay aux espaces administration.';

    public function handle(CapabilityService $capabilities): int
    {
        $names = [
            'payment.configuration.view',
            'payment.configuration.manage',
            'payment.configuration.test',
            'payment.configuration.activate',
            'wallet.deposit.reconcile',
        ];
        $spaces = 0;

        UserSpace::query()
            ->where('kind', SpaceKind::Administration->value)
            ->with('account')
            ->each(function (UserSpace $space) use ($capabilities, $names, &$spaces): void {
                foreach ($names as $name) {
                    $exists = $space->account->capabilityGrants()
                        ->where('space_id', $space->id)
                        ->where('capability', $name)
                        ->whereNull('revoked_at')
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $capabilities->grant(
                        account: $space->account,
                        capability: $name,
                        space: $space,
                        organizationId: $space->organization_id,
                        reason: 'Gestion sécurisée des identifiants GeniusPay',
                    );
                }

                $spaces++;
            });

        $this->info("Gestion GeniusPay autorisée pour {$spaces} espace(s) administration.");
        $this->warn('Les identifiants sont chiffrés en base et ne sont jamais réaffichés en clair.');
        $this->warn('L’activation Production reste verrouillée tant que GENIUSPAY_ALLOW_PRODUCTION_ACTIVATION=false.');

        return self::SUCCESS;
    }
}
