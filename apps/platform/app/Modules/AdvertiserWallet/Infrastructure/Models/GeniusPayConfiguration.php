<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class GeniusPayConfiguration extends Model
{
    use HasUlids;

    protected $table = 'geniuspay_configurations';

    protected $fillable = [
        'environment', 'api_key', 'api_secret', 'webhook_secret', 'updated_by',
    ];

    protected $hidden = ['api_key', 'api_secret', 'webhook_secret'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'api_secret' => 'encrypted',
            'webhook_secret' => 'encrypted',
        ];
    }
}
