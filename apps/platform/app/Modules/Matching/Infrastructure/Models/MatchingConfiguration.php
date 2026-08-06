<?php

declare(strict_types=1);

namespace App\Modules\Matching\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class MatchingConfiguration extends Model
{
    use HasUlids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $table = 'matching_configurations';

    protected $fillable = [
        'status', 'frequency_window_hours', 'frequency_max_per_window',
        'fatigue_threshold', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'frequency_window_hours' => 'integer',
            'frequency_max_per_window' => 'integer',
            'fatigue_threshold' => 'integer',
            'published_at' => 'immutable_datetime',
        ];
    }
}
