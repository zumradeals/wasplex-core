<?php

namespace App\Modules\Ledger\Infrastructure\Models;

use App\Modules\Ledger\Domain\Enums\LedgerAccountStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $account_type_id
 * @property string $code
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $unit
 * @property string $currency
 * @property LedgerAccountStatus $status
 * @property string|null $country_code
 * @property array<string, mixed>|null $metadata
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read LedgerAccountType $accountType
 */
class LedgerAccount extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_type_id',
        'code',
        'owner_type',
        'owner_id',
        'unit',
        'currency',
        'status',
        'country_code',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => LedgerAccountStatus::class,
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<LedgerAccountType, $this> */
    public function accountType(): BelongsTo
    {
        return $this->belongsTo(LedgerAccountType::class, 'account_type_id');
    }

    /** @return HasMany<LedgerEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'account_id');
    }
}
