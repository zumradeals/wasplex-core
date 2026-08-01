<?php

namespace App\Modules\Identity\Infrastructure\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $created_by_account_id
 * @property string $name
 * @property string $slug
 * @property string $kind
 * @property string $status
 * @property int $memberships_count
 * @property CarbonInterface|null $created_at
 * @property-read Account $creator
 * @property-read Collection<int, OrganizationMembership> $memberships
 * @property-read Collection<int, OrganizationInvitation> $invitations
 */
class Organization extends Model
{
    use HasUlids;

    protected $fillable = ['created_by_account_id', 'name', 'slug', 'kind', 'status'];

    /** @return BelongsTo<Account, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'created_by_account_id');
    }

    /** @return HasMany<OrganizationMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /** @return HasMany<OrganizationInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }
}
