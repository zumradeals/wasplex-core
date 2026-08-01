<?php

namespace App\Modules\Identity\Infrastructure\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $account_id
 * @property string|null $device_id
 * @property string|null $active_space_id
 * @property string $session_key_hash
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property CarbonImmutable|null $mfa_verified_at
 * @property CarbonImmutable $last_seen_at
 * @property CarbonImmutable|null $revoked_at
 * @property-read Account $account
 * @property-read UserSpace|null $activeSpace
 * @property-read AccountDevice|null $device
 */
class AccountSession extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_id',
        'device_id',
        'active_space_id',
        'session_key_hash',
        'ip_address',
        'user_agent',
        'mfa_verified_at',
        'last_seen_at',
        'revoked_at',
    ];

    protected $hidden = ['session_key_hash'];

    protected function casts(): array
    {
        return [
            'mfa_verified_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<UserSpace, $this> */
    public function activeSpace(): BelongsTo
    {
        return $this->belongsTo(UserSpace::class, 'active_space_id');
    }

    /** @return BelongsTo<AccountDevice, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(AccountDevice::class);
    }
}
