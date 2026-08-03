<?php

namespace App\Modules\Ledger\Infrastructure\Models;

use App\Modules\Ledger\Domain\Enums\EntryDirection;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property EntryDirection $normal_balance
 * @property string|null $description
 * @property CarbonImmutable $created_at
 */
class LedgerAccountType extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = ['code', 'name', 'normal_balance', 'description', 'created_at'];

    protected function casts(): array
    {
        return [
            'normal_balance' => EntryDirection::class,
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<LedgerAccount, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(LedgerAccount::class, 'account_type_id');
    }
}
