<?php

namespace App\Modules\Identity\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $account_id
 * @property string $display_name
 * @property string|null $avatar_path
 * @property-read Account $account
 */
class PersonalProfile extends Model
{
    use HasUlids;

    protected $fillable = ['account_id', 'display_name', 'avatar_path'];

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
