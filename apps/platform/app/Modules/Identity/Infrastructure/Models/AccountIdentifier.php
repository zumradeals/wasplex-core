<?php

namespace App\Modules\Identity\Infrastructure\Models;

use App\Modules\Identity\Domain\Enums\IdentifierType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $account_id
 * @property IdentifierType $type
 * @property string $normalized_value
 * @property string $display_value
 * @property bool $is_primary
 * @property CarbonImmutable|null $verified_at
 * @property-read Account $account
 */
class AccountIdentifier extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_id',
        'type',
        'normalized_value',
        'display_value',
        'is_primary',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => IdentifierType::class,
            'is_primary' => 'boolean',
            'verified_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
