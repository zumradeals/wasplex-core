<?php

declare(strict_types=1);

namespace App\Modules\Feed\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class FeedAdComment extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'feed_ad_comments';

    protected $fillable = ['account_id', 'campaign_id', 'body'];
}
