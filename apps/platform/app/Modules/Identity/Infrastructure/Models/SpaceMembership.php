<?php

namespace App\Modules\Identity\Infrastructure\Models;

use App\Modules\Identity\Domain\Enums\MembershipStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $space_id
 * @property string $account_id
 * @property MembershipStatus $status
 * @property string|null $title
 * @property CarbonImmutable|null $expires_at
 * @property-read UserSpace $space
 * @property-read Account $account
 */
class SpaceMembership extends Model
{
    use HasUlids;

    protected $fillable = ['space_id', 'account_id', 'status', 'title', 'expires_at'];

    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
            'expires_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<UserSpace, $this> */
    public function space(): BelongsTo
    {
        return $this->belongsTo(UserSpace::class, 'space_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
