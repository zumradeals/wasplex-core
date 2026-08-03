<?php

namespace App\Modules\Ledger\Infrastructure\Models;

use App\Modules\Ledger\Domain\Enums\LedgerJournalStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property LedgerJournalStatus $status
 * @property CarbonImmutable $created_at
 */
class LedgerJournal extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = ['code', 'name', 'status', 'created_at'];

    protected function casts(): array
    {
        return [
            'status' => LedgerJournalStatus::class,
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<LedgerTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(LedgerTransaction::class, 'journal_id');
    }
}
