<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PersonalProfile extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_id',
        'first_name',
        'last_name',
        'display_name',
        'photo_path',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
