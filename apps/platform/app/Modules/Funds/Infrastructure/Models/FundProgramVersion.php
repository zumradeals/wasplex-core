<?php

declare(strict_types=1);

namespace App\Modules\Funds\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FundProgramVersion extends Model
{
    use HasUlids;

    protected $table = 'fund_program_versions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'eligible_subscription_classes' => 'array',
            'published_at' => 'immutable_datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(FundProgram::class, 'fund_program_id');
    }
}
