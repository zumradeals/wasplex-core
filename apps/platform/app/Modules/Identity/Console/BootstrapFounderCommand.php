<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console;

use App\Modules\Identity\Infrastructure\Models\AccountIdentifier;
use App\Modules\Identity\Infrastructure\Models\CapabilityGrant;
use App\Modules\Identity\Infrastructure\Models\SpaceMembership;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repairs and synchronises the founder access of an existing account.
 *
 * This command is deliberately idempotent: production deployments may run it
 * repeatedly without creating duplicate memberships or active grants.
 */
final class BootstrapFounderCommand extends Command
{
    protected $signature = 'identity:bootstrap-founder {identifier : Email du fondateur}';

    protected $description = 'Synchronise l’espace admin et les capacités du compte fondateur existant';

    public function handle(): int
    {
        $identifierValue = trim((string) $this->argument('identifier'));
        $identifier = AccountIdentifier::query()
            ->where('type', 'email')
            ->whereRaw('lower(value) = ?', [mb_strtolower($identifierValue)])
            ->first();

        if ($identifier === null) {
            $this->components->error("Aucun compte email trouvé pour « {$identifierValue} ».");

            return self::FAILURE;
        }

        DB::transaction(function () use ($identifier): void {
            $accountId = $identifier->account_id;
            $adminSpace = UserSpace::query()
                ->where('space_type', UserSpace::TYPE_ADMIN)
                ->first()
                ?? UserSpace::create(['space_type' => UserSpace::TYPE_ADMIN, 'status' => 'active']);

            if (! $adminSpace->isActive()) {
                $adminSpace->update(['status' => 'active']);
            }

            SpaceMembership::query()->updateOrCreate(
                ['user_space_id' => $adminSpace->id, 'account_id' => $accountId],
                ['status' => 'active', 'is_default' => false, 'joined_at' => now()],
            );

            foreach (SeedFounderCommand::founderCapabilities() as $capabilityCode) {
                $grant = CapabilityGrant::query()
                    ->where('account_id', $accountId)
                    ->where('capability_code', $capabilityCode)
                    ->whereNull('scope_type')
                    ->whereNull('scope_id')
                    ->first() ?? new CapabilityGrant([
                        'account_id' => $accountId,
                        'capability_code' => $capabilityCode,
                    ]);

                $grant->fill([
                    'status' => 'active',
                    'starts_at' => now(),
                    'expires_at' => null,
                    'granted_by' => $accountId,
                    'revoked_at' => null,
                    'revoked_by' => null,
                ])->save();
            }
        });

        $this->components->info("Accès fondateur synchronisé pour « {$identifierValue} ».");

        return self::SUCCESS;
    }
}
