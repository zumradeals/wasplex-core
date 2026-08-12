<?php

declare(strict_types=1);

namespace App\Modules\Funds\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class FundMembership extends Model
{
    use HasUlids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_GRACE_PERIOD = 'grace_period';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_ENDED = 'ended';

    protected $table = 'fund_memberships';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'grace_started_at' => 'immutable_datetime',
            'grace_ends_at' => 'immutable_datetime',
            'suspended_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(FundProgram::class, 'fund_program_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(FundProgramVersion::class, 'program_version_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class, 'user_subscription_id');
    }

    public function mandate(): HasOne
    {
        return $this->hasOne(FundMandate::class, 'fund_membership_id')->latestOfMany();
    }
}
