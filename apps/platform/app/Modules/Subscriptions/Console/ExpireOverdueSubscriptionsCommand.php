<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Console;

use App\Modules\Subscriptions\Application\Services\SubscriptionService;
use Illuminate\Console\Command;

final class ExpireOverdueSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:expire-overdue';

    protected $description = 'Bascule vers Gratuit les abonnements dont la période est terminée sans renouvellement (docs/03 §19).';

    public function handle(SubscriptionService $subscriptions): int
    {
        $count = $subscriptions->expireOverdue();
        $this->info("{$count} abonnement(s) expiré(s).");

        return self::SUCCESS;
    }
}
