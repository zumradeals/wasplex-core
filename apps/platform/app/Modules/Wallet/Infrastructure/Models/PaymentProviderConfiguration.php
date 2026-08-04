<?php

namespace App\Modules\Wallet\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $provider
 * @property string $environment
 * @property string|null $api_key
 * @property string|null $api_secret
 * @property string|null $webhook_secret
 * @property string $base_url
 * @property list<string> $checkout_hosts
 * @property bool $is_active
 * @property string|null $last_test_status
 * @property string|null $last_test_message
 * @property CarbonImmutable|null $last_tested_at
 * @property CarbonImmutable|null $activated_at
 * @property string|null $updated_by_account_id
 * @property-read Account|null $updatedBy
 */
final class PaymentProviderConfiguration extends Model
{
    use HasUlids;

    protected $fillable = [
        'provider',
        'environment',
        'api_key',
        'api_secret',
        'webhook_secret',
        'base_url',
        'checkout_hosts',
        'is_active',
        'last_test_status',
        'last_test_message',
        'last_tested_at',
        'activated_at',
        'updated_by_account_id',
    ];

    protected $hidden = [
        'api_key',
        'api_secret',
        'webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'api_secret' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'checkout_hosts' => 'array',
            'is_active' => 'boolean',
            'last_tested_at' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'updated_by_account_id');
    }
}
