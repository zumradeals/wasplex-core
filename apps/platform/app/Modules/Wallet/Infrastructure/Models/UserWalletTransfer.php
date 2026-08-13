<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class UserWalletTransfer extends Model
{
    use HasUlids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_POSTED = 'posted';

    protected $table = 'user_wallet_transfers';

    protected $fillable = [
        'sender_account_id',
        'recipient_account_id',
        'amount_minor',
        'currency',
        'status',
        'ledger_transaction_id',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }
}
