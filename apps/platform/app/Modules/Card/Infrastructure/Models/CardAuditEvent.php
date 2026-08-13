<?php

declare(strict_types=1);

namespace App\Modules\Card\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class CardAuditEvent extends Model
{
    use HasUlids;

    protected $fillable = ['card_id', 'account_id', 'event_type', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
