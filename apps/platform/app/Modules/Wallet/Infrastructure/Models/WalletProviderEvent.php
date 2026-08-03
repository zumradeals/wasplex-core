<?php

namespace App\Modules\Wallet\Infrastructure\Models;

use App\Modules\Wallet\Domain\Enums\ProviderEventStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $provider
 * @property string $event_key
 * @property string $event_name
 * @property string|null $payment_reference
 * @property string $payload_hash
 * @property ProviderEventStatus $status
 * @property int $attempts
 * @property array<string, mixed> $payload
 * @property string|null $last_error
 * @property CarbonImmutable|null $occurred_at
 * @property CarbonImmutable|null $processed_at
 */
class WalletProviderEvent extends Model
{
    use HasUlids;

    protected $fillable = [
        'provider', 'event_key', 'event_name', 'payment_reference', 'payload_hash',
        'status', 'attempts', 'payload', 'last_error', 'occurred_at', 'processed_at',
    ];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return [
            'status' => ProviderEventStatus::class, 'attempts' => 'integer', 'payload' => 'array',
            'occurred_at' => 'immutable_datetime', 'processed_at' => 'immutable_datetime',
        ];
    }
}
