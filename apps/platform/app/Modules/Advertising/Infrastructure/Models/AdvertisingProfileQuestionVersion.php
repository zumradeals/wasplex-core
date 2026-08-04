<?php

namespace App\Modules\Advertising\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $advertising_profile_question_id
 * @property int $version
 * @property string $state
 * @property string $prompt
 * @property string $help_text
 * @property string $privacy_note
 * @property array<int, array{value:string,label:string}>|null $options
 * @property array<int, string> $purpose_codes
 * @property bool $optional
 * @property int|null $freshness_days
 * @property CarbonImmutable|null $effective_from
 * @property CarbonImmutable|null $effective_to
 * @property string|null $created_by_account_id
 * @property string|null $published_by_account_id
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read AdvertisingProfileQuestion $question
 * @property-read Account|null $creator
 * @property-read Account|null $publisher
 */
final class AdvertisingProfileQuestionVersion extends Model
{
    use HasUlids;

    protected $fillable = [
        'advertising_profile_question_id',
        'version',
        'state',
        'prompt',
        'help_text',
        'privacy_note',
        'options',
        'purpose_codes',
        'optional',
        'freshness_days',
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
            'options' => 'array',
            'purpose_codes' => 'array',
            'optional' => 'boolean',
            'freshness_days' => 'integer',
            'effective_from' => 'immutable_datetime',
            'effective_to' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<AdvertisingProfileQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(AdvertisingProfileQuestion::class, 'advertising_profile_question_id');
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
