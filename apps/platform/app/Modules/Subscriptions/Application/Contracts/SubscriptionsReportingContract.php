<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Contracts;

use App\Modules\Subscriptions\Application\ValueObjects\SubscriptionsSummary;

interface SubscriptionsReportingContract
{
    public function summary(): SubscriptionsSummary;
}
