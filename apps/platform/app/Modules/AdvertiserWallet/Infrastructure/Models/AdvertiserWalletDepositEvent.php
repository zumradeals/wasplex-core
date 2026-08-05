<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AdvertiserWalletDepositEvent extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public const STATUS_RECEIVED = 'received';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_DUPLICATED = 'duplicated';

    public const STATUS_FAILED = 'failed';

    protected $table = 'advertiser_wallet_deposit_events';

    protected $fillable = ['deposit_id', 'provider_code', 'event_type', 'provider_status_raw', 'signature_valid', 'status'];

    protected function casts(): array
    {
        return ['signature_valid' => 'boolean'];
    }

    public function deposit(): BelongsTo
    {
        return $this->belongsTo(AdvertiserWalletDeposit::class, 'deposit_id');
    }
}
