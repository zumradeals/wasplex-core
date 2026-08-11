<?php

declare(strict_types=1);

namespace App\Modules\Feed\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class FeedCampaignReward extends Model
{
    use HasUlids;

    protected $table = 'feed_campaign_rewards';

    protected $fillable = [
        'account_id',
        'campaign_id',
        'first_delivery_id',
        'organization_id',
        'economic_class',
        'reward_minor',
        'required_duration_ms',
        'ledger_transaction_id',
        'rewarded_at',
    ];

    protected function casts(): array
    {
        return [
            'reward_minor' => 'integer',
            'required_duration_ms' => 'integer',
            'rewarded_at' => 'immutable_datetime',
        ];
    }
}
