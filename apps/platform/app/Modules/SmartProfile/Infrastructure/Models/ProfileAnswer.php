<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProfileAnswer extends Model
{
    use HasUlids;

    public const SOURCE_DECLARED_BY_USER = 'declared_by_user';

    protected $table = 'profile_answers';

    protected $fillable = ['account_id', 'profile_taxonomy_id', 'source', 'answer_value', 'declared_at', 'withdrawn_at'];

    protected function casts(): array
    {
        return [
            'answer_value' => 'boolean',
            'declared_at' => 'immutable_datetime',
            'withdrawn_at' => 'immutable_datetime',
        ];
    }

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(ProfileTaxonomy::class, 'profile_taxonomy_id');
    }

    public function isActive(): bool
    {
        return $this->withdrawn_at === null;
    }
}
