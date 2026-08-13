<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class UserWalletDeposit extends Model
{
    use HasUlids;

    public const STATUS_CREATED = 'created';

    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CREDITED = 'credited';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    public const TERMINAL_STATUSES = [self::STATUS_CREDITED, self::STATUS_REJECTED, self::STATUS_EXPIRED];

    protected $table = 'user_wallet_deposits';

    protected $fillable = [
        'account_id',
        'ledger_transaction_id',
        'amount_minor',
        'currency',
        'status',
        'provider_code',
        'provider_reference',
        'checkout_url',
        'idempotency_key',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }
}
