<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LedgerAccount extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public const OWNER_TYPE_SYSTEM = 'system';

    public const OWNER_TYPE_IDENTITY_ACCOUNT = 'identity_account';

    public const OWNER_TYPE_ORGANIZATION = 'organization';

    /** Fixed owner_id sentinel for system-wide accounts (docs/CLAUDE.md #6: no cross-module FK). */
    public const SYSTEM_OWNER_ID = 'wasplex';

    protected $table = 'ledger_accounts';

    protected $fillable = ['code', 'account_type_id', 'owner_type', 'owner_id', 'currency', 'country_code', 'status'];

    public function accountType(): BelongsTo
    {
        return $this->belongsTo(LedgerAccountType::class, 'account_type_id');
    }
}
