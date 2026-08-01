<?php

namespace App\Modules\Identity\Infrastructure\Models;

use App\Modules\Identity\Domain\Enums\SpaceKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $account_id
 * @property string|null $organization_id
 * @property SpaceKind $kind
 * @property string $label
 * @property string $status
 * @property CarbonImmutable|null $last_opened_at
 * @property-read Account $account
 * @property-read Organization|null $organization
 * @property-read Collection<int, SpaceMembership> $memberships
 */
class UserSpace extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_id',
        'organization_id',
        'kind',
        'label',
        'status',
        'last_opened_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => SpaceKind::class,
            'last_opened_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<SpaceMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(SpaceMembership::class, 'space_id');
    }
}
