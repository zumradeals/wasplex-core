<?php

declare(strict_types=1);

namespace App\Modules\Feed\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FeedSession extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $table = 'feed_sessions';

    protected $fillable = ['account_id', 'started_at', 'ended_at'];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(FeedAdDelivery::class);
    }
}
