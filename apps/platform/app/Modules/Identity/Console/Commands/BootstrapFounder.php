<?php

namespace App\Modules\Identity\Console\Commands;

use App\Modules\Identity\Application\Services\CapabilityService;
use App\Modules\Identity\Domain\Enums\MembershipStatus;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\AccountIdentifier;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class BootstrapFounder extends Command
{
    protected $signature = 'identity:bootstrap-founder {identifier : E-mail du compte existant}';

    protected $description = 'Crée l’espace fondateur et lui accorde les capacités explicites du noyau.';

    public function handle(CapabilityService $capabilities): int
    {
        $identifier = AccountIdentifier::query()->where('normalized_value', Str::lower(trim((string) $this->argument('identifier'))))->first();

        if ($identifier === null) {
            $this->error('Aucun compte ne correspond à cet identifiant.');

            return self::FAILURE;
        }

        $account = $identifier->account()->firstOrFail();
        $space = $account->spaces()->firstOrCreate(
            ['kind' => SpaceKind::Administration->value, 'organization_id' => null],
            ['label' => 'Console fondateur', 'status' => 'active'],
        );

        $space->memberships()->updateOrCreate(
            ['account_id' => $account->id],
            ['status' => MembershipStatus::Active, 'title' => 'Fondateur'],
        );

        foreach ([
            'admin.dashboard.view', 'admin.accounts.view', 'admin.organizations.view',
            'admin.capabilities.grant', 'admin.capabilities.revoke',
            'wallet.ledger.view', 'wallet.correction.propose', 'wallet.correction.approve',
            'wallet.audit.view', 'wallet.deposit.review', 'wallet.reconciliation.manage',
            'wallet.configuration.manage',
            'economic.configuration.view', 'economic.configuration.manage',
            'economic.configuration.approve', 'economic.configuration.publish',
            'economic.configuration.suspend', 'economic.configuration.audit.view',
            'advertising.configuration.view', 'advertising.configuration.manage',
            'advertising.configuration.publish', 'advertising.match.audit.view',
        ] as $name) {
            if ($capabilities->allows($account, $name, $space)) {
                continue;
            }

            $capabilities->grant(
                account: $account,
                capability: $name,
                space: $space,
                reason: 'Initialisation nominative du fondateur',
            );
        }

        $this->info("Espace fondateur prêt pour {$identifier->display_value}.");
        $this->warn('Le MFA doit être configuré et confirmé avant l’accès à la console.');

        return self::SUCCESS;
    }
}
