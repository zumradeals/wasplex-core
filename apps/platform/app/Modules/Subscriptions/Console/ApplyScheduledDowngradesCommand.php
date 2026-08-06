<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Console;

use App\Modules\Subscriptions\Application\Services\SubscriptionService;
use Illuminate\Console\Command;

final class ApplyScheduledDowngradesCommand extends Command
{
    protected $signature = 'subscriptions:apply-scheduled-downgrades';

    protected $description = 'Applique les déclassements programmés dont le cycle en cours est terminé (docs/03 §18).';

    public function handle(SubscriptionService $subscriptions): int
    {
        $count = $subscriptions->applyScheduledDowngrades();
        $this->info("{$count} déclassement(s) appliqué(s).");

        return self::SUCCESS;
    }
}
