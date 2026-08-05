<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class UserSpace extends Model
{
    use HasUlids;

    public const TYPE_USER = 'user';

    public const TYPE_ADVERTISER = 'advertiser';

    public const TYPE_ADMIN = 'admin';

    protected $fillable = [
        'space_type',
        'organization_id',
        'owner_account_id',
        'status',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'owner_account_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(SpaceMembership::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
