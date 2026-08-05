<?php

namespace App\Modules\Distribution\Infrastructure\Models;

use App\Modules\Advertising\Infrastructure\Models\AdvertisingMatch;
use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAsset;
use App\Modules\Campaign\Infrastructure\Models\Campaign;
use App\Modules\Campaign\Infrastructure\Models\CampaignVersion;
use App\Modules\Distribution\Domain\Enums\AdvertisingDeliveryStatus;
use App\Modules\Identity\Infrastructure\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $advertising_delivery_lease_id
 * @property string $feed_session_id
 * @property string $account_id
 * @property string $advertising_match_id
 * @property string $campaign_id
 * @property string $campaign_version_id
 * @property string $creative_asset_id
 * @property string $subscription_quota_counter_id
 * @property string $economic_class_version_id
 * @property string $idempotency_key
 * @property AdvertisingDeliveryStatus $status
 * @property string $creative_type
 * @property string $media_reference
 * @property string|null $cta_reference
 * @property string $explanation_reference
 * @property array<string, mixed>|null $value_preview
 * @property string $context_hash
 * @property CarbonImmutable $prepared_at
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $invalidated_at
 * @property CarbonImmutable $expires_at
 */
final class AdvertisingDelivery extends Model
{
    use HasUlids;

    protected $fillable = [
        'advertising_delivery_lease_id',
        'feed_session_id',
        'account_id',
        'advertising_match_id',
        'campaign_id',
        'campaign_version_id',
        'creative_asset_id',
        'subscription_quota_counter_id',
        'economic_class_version_id',
        'idempotency_key',
        'status',
        'creative_type',
        'media_reference',
        'cta_reference',
        'explanation_reference',
        'value_preview',
        'context_hash',
        'prepared_at',
        'delivered_at',
        'invalidated_at',
        'expires_at',
    ];

    protected $hidden = ['account_id', 'idempotency_key', 'context_hash'];

    protected function casts(): array
    {
        return [
            'status' => AdvertisingDeliveryStatus::class,
            'value_preview' => 'array',
            'prepared_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'invalidated_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<AdvertisingDeliveryLease, $this> */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(AdvertisingDeliveryLease::class, 'advertising_delivery_lease_id');
    }

    /** @return BelongsTo<FeedSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(FeedSession::class, 'feed_session_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<AdvertisingMatch, $this> */
    public function match(): BelongsTo
    {
        return $this->belongsTo(AdvertisingMatch::class, 'advertising_match_id');
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

    /** @return BelongsTo<CreativeAsset, $this> */
    public function creative(): BelongsTo
    {
        return $this->belongsTo(CreativeAsset::class, 'creative_asset_id');
    }
}
