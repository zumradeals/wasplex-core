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
 * @property string $advertising_profile_question_id
 * @property string $advertising_taxonomy_id
 * @property int $version
 * @property array<string, mixed> $value
 * @property string $provenance
 * @property string $status
 * @property CarbonImmutable $answered_at
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $expires_at
 * @property string|null $replaces_answer_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Account $account
 * @property-read AdvertisingProfileQuestion $question
 * @property-read AdvertisingTaxonomy $taxonomy
 * @property-read AdvertisingProfileAnswer|null $replacedAnswer
 */
final class AdvertisingProfileAnswer extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_id',
        'advertising_profile_question_id',
        'advertising_taxonomy_id',
        'version',
        'value',
        'provenance',
        'status',
        'answered_at',
        'confirmed_at',
        'expires_at',
        'replaces_answer_id',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'value' => 'array',
            'answered_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<AdvertisingProfileQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(AdvertisingProfileQuestion::class, 'advertising_profile_question_id');
    }

    /** @return BelongsTo<AdvertisingTaxonomy, $this> */
    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(AdvertisingTaxonomy::class, 'advertising_taxonomy_id');
    }

    /** @return BelongsTo<AdvertisingProfileAnswer, $this> */
    public function replacedAnswer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_answer_id');
    }
}
