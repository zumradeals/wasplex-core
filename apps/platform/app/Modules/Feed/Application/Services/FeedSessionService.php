<?php

declare(strict_types=1);

namespace App\Modules\Feed\Application\Services;

use App\Modules\Feed\Infrastructure\Models\FeedSession;
use Illuminate\Support\Carbon;

final class FeedSessionService
{
    public function start(string $accountId): FeedSession
    {
        return FeedSession::query()->create(['account_id' => $accountId, 'started_at' => Carbon::now('UTC')]);
    }
}
