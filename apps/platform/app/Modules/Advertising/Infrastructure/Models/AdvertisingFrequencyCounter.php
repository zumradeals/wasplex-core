<?php

namespace App\Modules\Advertising\Infrastructure\Models;

use App\Modules\Campaign\Infrastructure\Models\Campaign;
use App\Modules\Identity\Infrastructure\Models\Account;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $account_id
 * @property string $campaign_id
 * @property CarbonImmutable $window_start
 * @property CarbonImmutable $window_end
 * @property int $impressions
 * @property int $fatigue_score
 * @property CarbonImmutable|null $last_impression_at
 * @property int $version
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Account $account
 * @property-read Campaign $campaign
 */
final class AdvertisingFrequencyCounter extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_id',
        'campaign_id',
        'window_start',
        'window_end',
        'impressions',
        'fatigue_score',
        'last_impression_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'window_start' => 'immutable_datetime',
            'window_end' => 'immutable_datetime',
            'impressions' => 'integer',
            'fatigue_score' => 'integer',
            'last_impression_at' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
