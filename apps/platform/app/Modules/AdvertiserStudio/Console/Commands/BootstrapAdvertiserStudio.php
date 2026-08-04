<?php

namespace App\Modules\AdvertiserStudio\Console\Commands;

use App\Modules\AdvertiserStudio\Application\Services\AdvertiserProfileService;
use App\Modules\Identity\Application\Services\CapabilityService;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Console\Command;

final class BootstrapAdvertiserStudio extends Command
{
    protected $signature = 'studio:bootstrap';

    protected $description = 'Initialise les profils et capacités P005 des espaces annonceurs existants.';

    public function handle(AdvertiserProfileService $profiles, CapabilityService $capabilities): int
    {
        $count = 0;
        $studioCapabilities = [
            'advertiser.profile.view',
            'advertiser.profile.manage',
            'advertiser.brand.view',
            'advertiser.brand.manage',
            'advertiser.media.view',
            'advertiser.media.upload',
            'advertiser.media.manage',
        ];

        UserSpace::query()
            ->where('kind', SpaceKind::Advertiser->value)
            ->with(['account', 'organization'])
            ->each(function (UserSpace $space) use ($profiles, $capabilities, $studioCapabilities, &$count): void {
                $account = $space->account;
                $profiles->ensure($space, $account);

                foreach ($studioCapabilities as $capability) {
                    if ($capabilities->allows($account, $capability, $space)) {
                        continue;
                    }

                    $capabilities->grant(
                        account: $account,
                        capability: $capability,
                        space: $space,
                        organizationId: $space->organization_id,
                        reason: 'Initialisation du Studio Annonceur P005',
                    );
                }

                $count++;
            });

        $this->info("Studio Annonceur initialisé pour {$count} espace(s).");

        return self::SUCCESS;
    }
}
