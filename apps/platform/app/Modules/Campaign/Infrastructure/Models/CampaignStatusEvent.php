<?php

namespace App\Modules\Campaign\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $campaign_id
 * @property string|null $actor_account_id
 * @property string|null $from_status
 * @property string $to_status
 * @property string|null $reason
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $occurred_at
 * @property-read Campaign $campaign
 * @property-read Account|null $actor
 */
final class CampaignStatusEvent extends Model
{
    use HasUlids;

    protected $fillable = [
        'campaign_id',
        'actor_account_id',
        'from_status',
        'to_status',
        'reason',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'actor_account_id');
    }
}
