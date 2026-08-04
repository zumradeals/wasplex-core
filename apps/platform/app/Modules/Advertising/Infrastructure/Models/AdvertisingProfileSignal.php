<?php

namespace App\Modules\Advertising\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $account_id
 * @property string $advertising_taxonomy_id
 * @property int $version
 * @property array<string, mixed> $value
 * @property string $source
 * @property string $status
 * @property float|null $confidence
 * @property string|null $market_code
 * @property string|null $explanation
 * @property array<string, mixed>|null $evidence
 * @property bool $requires_user_confirmation
 * @property bool $created_by_ai
 * @property CarbonImmutable|null $observed_at
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $expires_at
 * @property string|null $replaces_signal_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Account $account
 * @property-read AdvertisingTaxonomy $taxonomy
 * @property-read AdvertisingProfileSignal|null $replaces
 */
final class AdvertisingProfileSignal extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_id',
        'advertising_taxonomy_id',
        'version',
        'value',
        'source',
        'status',
        'confidence',
        'market_code',
        'explanation',
        'evidence',
        'requires_user_confirmation',
        'created_by_ai',
        'observed_at',
        'confirmed_at',
        'expires_at',
        'replaces_signal_id',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'confidence' => 'float',
            'evidence' => 'array',
            'requires_user_confirmation' => 'boolean',
            'created_by_ai' => 'boolean',
            'observed_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<AdvertisingTaxonomy, $this> */
    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(AdvertisingTaxonomy::class);
    }

    /** @return BelongsTo<AdvertisingProfileSignal, $this> */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_signal_id');
    }
}
