<?php

declare(strict_types=1);

namespace App\Modules\Feed\Application\Services;

use App\Modules\Identity\Infrastructure\Models\OrganizationMembership;
use Illuminate\Support\Carbon;

/**
 * Economic integrity rule for the rewarded Feed: an advertiser can never
 * earn from a campaign owned by an organization they actively belong to.
 * This covers both the founding owner and every active team member.
 */
final class FeedRewardEligibilityService
{
    public function isBlockedForOrganization(string $accountId, string $organizationId): bool
    {
        $now = Carbon::now('UTC');

        return OrganizationMembership::query()
            ->where('organization_id', $organizationId)
            ->where('account_id', $accountId)
            ->where('status', 'active')
            ->where(function ($query) use ($now): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->exists();
    }
}
