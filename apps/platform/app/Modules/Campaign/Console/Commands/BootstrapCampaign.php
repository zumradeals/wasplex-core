<?php

namespace App\Modules\Campaign\Console\Commands;

use App\Modules\Campaign\Application\Services\CampaignService;
use App\Modules\Identity\Application\Services\CapabilityService;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Console\Command;

final class BootstrapCampaign extends Command
{
    protected $signature = 'campaign:bootstrap';

    protected $description = 'Initialise le catalogue sandbox et les capacités P006 des espaces annonceurs.';

    public function handle(CampaignService $campaigns, CapabilityService $capabilities): int
    {
        $campaigns->ensurePriceCatalog();
        $count = 0;
        $campaignCapabilities = [
            'advertiser.campaign.view',
            'advertiser.campaign.create',
            'advertiser.campaign.manage',
            'advertiser.campaign.quote',
            'advertiser.campaign.fund',
            'advertiser.campaign.submit',
        ];

        UserSpace::query()
            ->where('kind', SpaceKind::Advertiser->value)
            ->with('account')
            ->each(function (UserSpace $space) use ($capabilities, $campaignCapabilities, &$count): void {
                foreach ($campaignCapabilities as $capability) {
                    if ($capabilities->allows($space->account, $capability, $space)) {
                        continue;
                    }

                    $capabilities->grant(
                        account: $space->account,
                        capability: $capability,
                        space: $space,
                        organizationId: $space->organization_id,
                        reason: 'Initialisation du module Campagnes P006',
                    );
                }

                $count++;
            });

        $this->info("P006 initialisé pour {$count} espace(s) annonceur(s).");

        return self::SUCCESS;
    }
}
