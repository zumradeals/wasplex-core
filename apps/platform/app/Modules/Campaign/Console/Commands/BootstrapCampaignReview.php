<?php

namespace App\Modules\Campaign\Console\Commands;

use App\Modules\Campaign\Application\Services\CampaignReviewService;
use App\Modules\Identity\Application\Services\CapabilityService;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Console\Command;

final class BootstrapCampaignReview extends Command
{
    protected $signature = 'campaign-review:bootstrap';

    protected $description = 'Initialise les dossiers P007 et les capacités de revue des espaces administration.';

    public function handle(CampaignReviewService $reviews, CapabilityService $capabilities): int
    {
        $administrationSpaces = 0;
        $reviewCapabilities = [
            'campaign.review.view',
            'campaign.review.request_changes',
            'campaign.review.approve',
            'campaign.review.reject',
            'campaign.suspend',
        ];

        UserSpace::query()
            ->where('kind', SpaceKind::Administration->value)
            ->with('account')
            ->each(function (UserSpace $space) use ($capabilities, $reviewCapabilities, &$administrationSpaces): void {
                foreach ($reviewCapabilities as $capability) {
                    if ($capabilities->allows($space->account, $capability, $space)) {
                        continue;
                    }

                    $capabilities->grant(
                        account: $space->account,
                        capability: $capability,
                        space: $space,
                        organizationId: $space->organization_id,
                        reason: 'Initialisation de la revue administrative P007',
                    );
                }

                $administrationSpaces++;
            });

        $createdCases = $reviews->ensureSubmittedCases();

        $this->info("P007 initialisé pour {$administrationSpaces} espace(s) administration.");
        $this->info("Dossiers de revue créés pour {$createdCases} campagne(s) déjà soumise(s).");

        return self::SUCCESS;
    }
}
