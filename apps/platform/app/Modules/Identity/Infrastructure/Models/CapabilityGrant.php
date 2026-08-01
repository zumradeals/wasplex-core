<?php

namespace App\Modules\Identity\Infrastructure\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $account_id
 * @property string|null $space_id
 * @property string|null $organization_id
 * @property string|null $granted_by_account_id
 * @property string $capability
 * @property array<string, mixed>|null $scope
 * @property CarbonImmutable|null $valid_from
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $reason
 * @property-read Account $account
 * @property-read UserSpace|null $space
 */
class CapabilityGrant extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_id',
        'space_id',
        'organization_id',
        'granted_by_account_id',
        'capability',
        'scope',
        'valid_from',
        'expires_at',
        'revoked_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'scope' => 'array',
            'valid_from' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<UserSpace, $this> */
    public function space(): BelongsTo
    {
        return $this->belongsTo(UserSpace::class, 'space_id');
    }
}
