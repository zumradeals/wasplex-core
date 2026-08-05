<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class LedgerJournal extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public const CODE_MAIN = 'GRAND_LIVRE_PRINCIPAL';

    protected $table = 'ledger_journals';

    protected $fillable = ['code', 'label', 'status'];
}
