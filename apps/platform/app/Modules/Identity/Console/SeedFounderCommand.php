<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console;

use App\Modules\Identity\Application\Services\AccountRegistrationService;
use App\Modules\Identity\Infrastructure\Models\AccountIdentifier;
use App\Modules\Identity\Infrastructure\Models\CapabilityGrant;
use App\Modules\Identity\Infrastructure\Models\SpaceMembership;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bootstraps the founder's admin space and explicit capability grants.
 * Docs/12 #78-#80: no implicit "admin = everything" role — even the
 * founder's rights are auditable rows, granted here as a one-time,
 * nominative, reversible bootstrap (docs/24 "intervention exceptionnelle").
 */
final class SeedFounderCommand extends Command
{
    protected $signature = 'identity:seed-founder
        {--identifier= : Email du fondateur}
        {--password= : Mot de passe (min. 8 caractères)}
        {--country=CI : Code pays ISO2}';

    protected $description = 'Crée le compte fondateur avec ses capacités administratives explicites';

    public function handle(AccountRegistrationService $registration): int
    {
        $identifier = $this->option('identifier') ?? $this->ask('Email du fondateur');
        $password = $this->option('password') ?? $this->secret('Mot de passe (min. 8 caractères)');
        $country = strtoupper((string) $this->option('country'));

        if (! is_string($identifier) || $identifier === '') {
            $this->components->error('Identifiant requis.');

            return self::FAILURE;
        }

        if (! is_string($password) || strlen($password) < 8) {
            $this->components->error('Mot de passe requis (8 caractères minimum).');

            return self::FAILURE;
        }

        if (AccountIdentifier::query()->where('value', $identifier)->exists()) {
            $this->components->error("Un compte existe déjà pour « {$identifier} ».");

            return self::FAILURE;
        }

        DB::transaction(function () use ($registration, $identifier, $password, $country): void {
            $account = $registration->register('email', $identifier, $password, $country);

            $adminSpace = UserSpace::query()
                ->where('space_type', UserSpace::TYPE_ADMIN)
                ->first()
                ?? UserSpace::create(['space_type' => UserSpace::TYPE_ADMIN, 'status' => 'active']);

            SpaceMembership::create([
                'user_space_id' => $adminSpace->id,
                'account_id' => $account->id,
                'status' => 'active',
                'is_default' => false,
                'joined_at' => now(),
            ]);

            foreach ($this->founderCapabilities() as $capability) {
                CapabilityGrant::create([
                    'account_id' => $account->id,
                    'capability_code' => $capability,
                    'status' => 'active',
                    'starts_at' => now(),
                    'granted_by' => $account->id,
                ]);
            }

            $this->components->info("Fondateur créé : {$account->id}");
        });

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function founderCapabilities(): array
    {
        return [
            'admin.dashboard.view',
            'admin.capabilities.grant',
            'admin.capabilities.revoke',
            'admin.audit.view',
            // docs/chantiers/P002-CHANTIER.md: capacités Grand Livre.
            // Identity ne connaît pas leur signification, seulement leur
            // code (le système de capacités est agnostique du domaine).
            'wallet.ledger.view',
            'wallet.correction.propose',
            'wallet.correction.approve',
            'wallet.audit.view',
            // docs/chantiers/P005-CHANTIER.md: capacités Studio Annonceur.
            'admin.advertisers.manage',
            'admin.brands.moderate',
            'admin.advertiser-wallet.supervised-deposit',
            // docs/chantiers/P004-CHANTIER.md: capacités Abonnements/Classes.
            'admin.subscriptions.plans.manage',
            'admin.subscriptions.classes.manage',
            // docs/chantiers/P006-CHANTIER.md: catalogue de prix publicitaire.
            'admin.advertising.pricing.manage',
            // docs/chantiers/P007-CHANTIER.md: revue administrative des campagnes.
            'admin.campaign-reviews.view',
            'admin.campaign-reviews.decide',
            'admin.campaigns.suspend',
        ];
    }
}
