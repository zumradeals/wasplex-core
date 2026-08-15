<?php

declare(strict_types=1);

namespace App\Modules\Card\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CardOperation extends Model
{
    use HasUlids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_POSTED = 'posted';

    protected $fillable = [
        'payer_account_id',
        'beneficiary_account_id',
        'beneficiary_card_id',
        'qr_token_id',
        'amount_minor',
        'currency',
        'note',
        'status',
        'idempotency_key',
        'ledger_transaction_id',
        'receipt_reference',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'posted_at' => 'datetime',
        ];
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payer_account_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'beneficiary_account_id');
    }

    public function beneficiaryCard(): BelongsTo
    {
        return $this->belongsTo(Card::class, 'beneficiary_card_id');
    }

    public function qrToken(): BelongsTo
    {
        return $this->belongsTo(CardQrToken::class, 'qr_token_id');
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'ledger_transaction_id');
    }
}
