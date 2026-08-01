<?php

namespace App\Modules\Identity\Application\Services;

use App\Modules\Identity\Domain\Events\CapabilityGranted;
use App\Modules\Identity\Domain\Events\CapabilityRevoked;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\CapabilityGrant;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Support\Facades\DB;

final class CapabilityService
{
    public function allows(Account $account, string $capability, ?UserSpace $space = null): bool
    {
        return CapabilityGrant::query()
            ->where('account_id', $account->id)
            ->where('capability', $capability)
            ->whereNull('revoked_at')
            ->where(static function ($query): void {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(static function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->when($space, static function ($query, UserSpace $space): void {
                $query->where(static function ($scope) use ($space): void {
                    $scope->whereNull('space_id')->orWhere('space_id', $space->id);
                });
                $query->where(static function ($scope) use ($space): void {
                    $scope->whereNull('organization_id')->orWhere('organization_id', $space->organization_id);
                });
            })
            ->exists();
    }

    /** @param array<string, mixed>|null $scope */
    public function grant(
        Account $account,
        string $capability,
        ?Account $grantedBy = null,
        ?UserSpace $space = null,
        ?string $organizationId = null,
        ?array $scope = null,
        ?\DateTimeInterface $expiresAt = null,
        ?string $reason = null,
    ): CapabilityGrant {
        return DB::transaction(function () use (
            $account,
            $capability,
            $grantedBy,
            $space,
            $organizationId,
            $scope,
            $expiresAt,
            $reason,
        ): CapabilityGrant {
            $grant = CapabilityGrant::query()->create([
                'account_id' => $account->id,
                'space_id' => $space?->id,
                'organization_id' => $organizationId,
                'granted_by_account_id' => $grantedBy?->id,
                'capability' => $capability,
                'scope' => $scope,
                'valid_from' => now(),
                'expires_at' => $expiresAt,
                'reason' => $reason,
            ]);

            DB::afterCommit(static fn () => event(new CapabilityGranted($grant->id, $account->id, $capability)));

            return $grant;
        });
    }

    public function revoke(CapabilityGrant $grant): void
    {
        DB::transaction(function () use ($grant): void {
            $grant->forceFill(['revoked_at' => now()])->save();
            DB::afterCommit(static fn () => event(new CapabilityRevoked($grant->id, $grant->account_id, $grant->capability)));
        });
    }
}
