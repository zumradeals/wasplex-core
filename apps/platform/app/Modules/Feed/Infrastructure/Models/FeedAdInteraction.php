<?php

declare(strict_types=1);

namespace App\Modules\Feed\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class FeedAdInteraction extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public const TYPE_LIKE = 'like';

    public const TYPE_SAVE = 'save';

    public const TYPE_SHARE = 'share';

    protected $table = 'feed_ad_interactions';

    protected $fillable = ['account_id', 'campaign_id', 'type'];
}
