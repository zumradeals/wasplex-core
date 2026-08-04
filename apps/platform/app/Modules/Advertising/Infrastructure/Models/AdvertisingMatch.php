<?php

namespace App\Modules\Advertising\Infrastructure\Models;

use App\Modules\Campaign\Infrastructure\Models\Campaign;
use App\Modules\Campaign\Infrastructure\Models\CampaignVersion;
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
 * @property string $campaign_version_id
 * @property string $advertising_segment_id
 * @property string $idempotency_key
 * @property string $decision
 * @property int $score_basis_points
 * @property string|null $score_band
 * @property string $rule_version
 * @property array<int, string> $explanation_tokens
 * @property array<int, string> $exclusion_codes
 * @property array<string, mixed> $frequency_state
 * @property CarbonImmutable $evaluated_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Account $account
 * @property-read Campaign $campaign
 * @property-read CampaignVersion $campaignVersion
 * @property-read AdvertisingSegment $segment
 */
final class AdvertisingMatch extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_id',
        'campaign_id',
        'campaign_version_id',
        'advertising_segment_id',
        'idempotency_key',
        'decision',
        'score_basis_points',
        'score_band',
        'rule_version',
        'explanation_tokens',
        'exclusion_codes',
        'frequency_state',
        'evaluated_at',
    ];

    protected $hidden = ['account_id', 'idempotency_key'];

    protected function casts(): array
    {
        return [
            'score_basis_points' => 'integer',
            'explanation_tokens' => 'array',
            'exclusion_codes' => 'array',
            'frequency_state' => 'array',
            'evaluated_at' => 'immutable_datetime',
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

    /** @return BelongsTo<CampaignVersion, $this> */
    public function campaignVersion(): BelongsTo
    {
        return $this->belongsTo(CampaignVersion::class);
    }

    /** @return BelongsTo<AdvertisingSegment, $this> */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(AdvertisingSegment::class, 'advertising_segment_id');
    }
}
