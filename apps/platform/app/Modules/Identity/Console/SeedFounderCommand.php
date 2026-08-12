<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\AccountIdentifier;
use App\Modules\Identity\Infrastructure\Models\CapabilityGrant;
use App\Modules\Identity\Infrastructure\Models\PersonalProfile;
use App\Modules\Identity\Infrastructure\Models\SpaceMembership;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class SeedFounderCommand extends Command
{
    protected $signature = 'identity:seed-founder {identifier : Email du fondateur} {--password= : Mot de passe initial}';

    protected $description = 'Crée le compte fondateur initial et son espace admin';

    public function handle(): int
    {
        if (Account::query()->exists()) {
            $this->components->error('Le bootstrap fondateur ne peut être exécuté que sur une base Identity vide.');

            return self::FAILURE;
        }

        $identifierValue = trim((string) $this->argument('identifier'));
        $password = trim((string) ($this->option('password') ?: ''));

        if ($password === '') {
            $password = (string) $this->secret('Mot de passe initial du fondateur');
        }

        if (mb_strlen($password) < 12) {
            $this->components->error('Le mot de passe initial doit contenir au moins 12 caractères.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($identifierValue, $password): void {
            $account = Account::create([
                'status' => 'active',
                'password' => Hash::make($password),
                'country_code' => 'CI',
                'language' => 'fr',
            ]);

            AccountIdentifier::create([
                'account_id' => $account->id,
                'type' => 'email',
                'value' => $identifierValue,
                'is_primary' => true,
                'verified_at' => now(),
            ]);

            PersonalProfile::create(['account_id' => $account->id]);

            $adminSpace = UserSpace::create([
                'space_type' => UserSpace::TYPE_ADMIN,
                'status' => 'active',
            ]);

            SpaceMembership::create([
                'user_space_id' => $adminSpace->id,
                'account_id' => $account->id,
                'status' => 'active',
                'is_default' => true,
                'joined_at' => now(),
            ]);

            foreach (self::founderCapabilities() as $capability) {
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
    public static function founderCapabilities(): array
    {
        return [
            'admin.dashboard.view',
            'admin.capabilities.grant',
            'admin.capabilities.revoke',
            'admin.audit.view',
            // docs/chantiers/P017-CHANTIER.md: écran Utilisateurs.
            'admin.accounts.view',
            'admin.accounts.restrict',
            'admin.identity-verifications.view',
            'admin.identity-verifications.decide',
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
            // docs/chantiers/P008-CHANTIER.md: SmartProfile et Matching.
            'admin.smartprofile.taxonomies.manage',
            'admin.smartprofile.consents.manage',
            'admin.matching.configuration.manage',
            'admin.matching.audit.view',
            // docs/chantiers/P009-CHANTIER.md: Feed et attention.
            'admin.feed.dashboard.view',
            'admin.feed.risk.review',
            // docs/chantiers/P011-B-RAPPROCHEMENT.md: rapprochement GeniusPay.
            'admin.reconciliation.review',
        ];
    }
}
