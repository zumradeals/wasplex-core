<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A family of accounts (docs/06-wallet-et-grand-livre-wasplex.md §7):
 * ASSET, LIABILITY_USER, REVENUE, EXPENSE, RESERVATION, CLEARING, PARTNER,
 * FONDS, REFUND. Purely descriptive metadata — normal_balance is not
 * enforced against entry directions in P002 (only the overall
 * debits = credits invariant is).
 */
final class LedgerAccountType extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'ledger_account_types';

    protected $fillable = ['code', 'label', 'normal_balance', 'description'];
}
