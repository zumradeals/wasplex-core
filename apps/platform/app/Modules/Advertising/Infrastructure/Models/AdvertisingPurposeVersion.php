<?php

namespace App\Modules\Advertising\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $advertising_purpose_id
 * @property int $version
 * @property string $state
 * @property string $title
 * @property string $description
 * @property string|null $refusal_consequence
 * @property bool $consent_required
 * @property bool $requires_new_decision
 * @property CarbonImmutable|null $effective_from
 * @property CarbonImmutable|null $effective_to
 * @property string|null $created_by_account_id
 * @property string|null $published_by_account_id
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read AdvertisingPurpose $purpose
 * @property-read Account|null $creator
 * @property-read Account|null $publisher
 */
final class AdvertisingPurposeVersion extends Model
{
    use HasUlids;

    protected $fillable = [
        'advertising_purpose_id',
        'version',
        'state',
        'title',
        'description',
        'refusal_consequence',
        'consent_required',
        'requires_new_decision',
        'effective_from',
        'effective_to',
        'created_by_account_id',
        'published_by_account_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'consent_required' => 'boolean',
            'requires_new_decision' => 'boolean',
            'effective_from' => 'immutable_datetime',
            'effective_to' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<AdvertisingPurpose, $this> */
    public function purpose(): BelongsTo
    {
        return $this->belongsTo(AdvertisingPurpose::class, 'advertising_purpose_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'created_by_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'published_by_account_id');
    }
}
