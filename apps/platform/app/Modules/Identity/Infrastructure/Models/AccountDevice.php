<?php

namespace App\Modules\Identity\Infrastructure\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $account_id
 * @property string $name
 * @property string|null $platform
 * @property string|null $fingerprint_hash
 * @property CarbonImmutable|null $verified_at
 * @property CarbonImmutable|null $last_seen_at
 * @property CarbonImmutable|null $revoked_at
 * @property-read Account $account
 */
class AccountDevice extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_id',
        'name',
        'platform',
        'fingerprint_hash',
        'verified_at',
        'last_seen_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
