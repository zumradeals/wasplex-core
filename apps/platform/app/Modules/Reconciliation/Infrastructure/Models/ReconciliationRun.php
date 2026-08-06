<?php

declare(strict_types=1);

namespace App\Modules\Reconciliation\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ReconciliationRun extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $table = 'reconciliation_runs';

    protected $fillable = ['triggered_by', 'started_at', 'completed_at', 'total_checked', 'summary'];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'total_checked' => 'integer',
            'summary' => 'array',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ReconciliationEntry::class, 'run_id');
    }
}
