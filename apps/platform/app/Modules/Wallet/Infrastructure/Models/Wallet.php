<?php

namespace App\Modules\Wallet\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\Organization;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use App\Modules\Ledger\Infrastructure\Models\LedgerAccount;
use App\Modules\Wallet\Domain\Enums\WalletKind;
use App\Modules\Wallet\Domain\Enums\WalletStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $account_id
 * @property string $space_id
 * @property string|null $organization_id
 * @property string|null $available_ledger_account_id
 * @property string|null $reserved_ledger_account_id
 * @property string|null $pending_ledger_account_id
 * @property WalletKind $kind
 * @property string $unit
 * @property string $currency
 * @property WalletStatus $status
 * @property-read Account $account
 * @property-read UserSpace $space
 * @property-read Organization|null $organization
 * @property-read WalletBalanceProjection|null $projection
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WalletOperation> $operations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WalletReservation> $reservations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WalletDeposit> $deposits
 */
class Wallet extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_id', 'space_id', 'organization_id', 'available_ledger_account_id',
        'reserved_ledger_account_id', 'pending_ledger_account_id', 'kind', 'unit',
        'currency', 'status',
    ];

    protected function casts(): array
    {
        return ['kind' => WalletKind::class, 'status' => WalletStatus::class];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<UserSpace, $this> */
    public function space(): BelongsTo
    {
        return $this->belongsTo(UserSpace::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<LedgerAccount, $this> */
    public function availableLedgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'available_ledger_account_id');
    }

    /** @return BelongsTo<LedgerAccount, $this> */
    public function reservedLedgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'reserved_ledger_account_id');
    }

    /** @return BelongsTo<LedgerAccount, $this> */
    public function pendingLedgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'pending_ledger_account_id');
    }

    /** @return HasOne<WalletBalanceProjection, $this> */
    public function projection(): HasOne
    {
        return $this->hasOne(WalletBalanceProjection::class);
    }

    /** @return HasMany<WalletOperation, $this> */
    public function operations(): HasMany
    {
        return $this->hasMany(WalletOperation::class);
    }

    /** @return HasMany<WalletReservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(WalletReservation::class);
    }

    /** @return HasMany<WalletDeposit, $this> */
    public function deposits(): HasMany
    {
        return $this->hasMany(WalletDeposit::class);
    }
}
