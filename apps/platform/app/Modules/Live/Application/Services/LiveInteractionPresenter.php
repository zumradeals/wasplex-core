<?php

declare(strict_types=1);

namespace App\Modules\Live\Application\Services;

use App\Modules\Live\Infrastructure\Models\LiveComment;
use Illuminate\Support\Str;

final class LiveInteractionPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function comment(LiveComment $comment): array
    {
        $comment->loadMissing(['account.profile', 'parent.account.profile']);
        $parent = $comment->parent;

        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'status' => $comment->hidden_at === null ? 'visible' : 'hidden',
            'is_pinned' => $comment->pinned_at !== null,
            'created_at' => $comment->created_at?->toIso8601String(),
            'author' => [
                'account_id' => $comment->account_id,
                'display_name' => $comment->account->profile?->resolvedDisplayName() ?? 'Membre Wasplex',
            ],
            'reply_to' => $parent === null ? null : [
                'id' => $parent->id,
                'display_name' => $parent->account->profile?->resolvedDisplayName() ?? 'Membre Wasplex',
                'excerpt' => Str::limit($parent->body, 80),
            ],
        ];
    }

    /**
     * @return array{comments_enabled: bool, slow_mode_seconds: int}
     */
    public static function settings(bool $commentsEnabled, int $slowModeSeconds): array
    {
        return [
            'comments_enabled' => $commentsEnabled,
            'slow_mode_seconds' => $slowModeSeconds,
        ];
    }
}
