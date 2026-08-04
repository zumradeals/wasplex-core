<?php

namespace App\Modules\Advertising\Infrastructure\Models;

use App\Modules\Advertising\Domain\Enums\AdvertisingConsentStatus;
use App\Modules\Identity\Infrastructure\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $account_id
 * @property string $advertising_purpose_id
 * @property string $advertising_purpose_version_id
 * @property AdvertisingConsentStatus $status
 * @property string $channel
 * @property array<string, mixed>|null $context
 * @property CarbonImmutable $decided_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Account $account
 * @property-read AdvertisingPurpose $purpose
 * @property-read AdvertisingPurposeVersion $purposeVersion
 */
final class AdvertisingConsent extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_id',
        'advertising_purpose_id',
        'advertising_purpose_version_id',
        'status',
        'channel',
        'context',
        'decided_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AdvertisingConsentStatus::class,
            'context' => 'array',
            'decided_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<AdvertisingPurpose, $this> */
    public function purpose(): BelongsTo
    {
        return $this->belongsTo(AdvertisingPurpose::class, 'advertising_purpose_id');
    }

    /** @return BelongsTo<AdvertisingPurposeVersion, $this> */
    public function purposeVersion(): BelongsTo
    {
        return $this->belongsTo(AdvertisingPurposeVersion::class, 'advertising_purpose_version_id');
    }
}
