<?php

namespace App\Modules\Advertising\Infrastructure\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string $currency
 * @property string $timezone
 * @property string $default_locale
 * @property array<int, string>|null $supported_locales
 * @property bool $is_default
 * @property string $status
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class AdvertisingMarket extends Model
{
    use HasUlids;

    protected $fillable = [
        'code',
        'name',
        'currency',
        'timezone',
        'default_locale',
        'supported_locales',
        'is_default',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'supported_locales' => 'array',
            'is_default' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
