<?php

declare(strict_types=1);

namespace App\Modules\Feed\Application\Services;

use App\Modules\Feed\Infrastructure\Models\FeedAdComment;
use App\Modules\Feed\Infrastructure\Models\FeedAdInteraction;
use Illuminate\Database\Eloquent\Collection;

/**
 * docs/08-feed-principal-wasplex.md §31 : « aimer, commenter, partager,
 * enregistrer » — actions n'accordant jamais de WP automatiquement.
 * J'aime/favoris basculent (toggle) ; partage s'accumule (chaque partage
 * compte) ; commentaire est minimal, sans modération (docs/chantiers/
 * P009-CHANTIER.md §2.6).
 */
final class FeedInteractionService
{
    /**
     * @return array{active: bool, count: int}
     */
    public function toggleLike(string $accountId, string $campaignId): array
    {
        return $this->toggle($accountId, $campaignId, FeedAdInteraction::TYPE_LIKE);
    }

    /**
     * @return array{active: bool, count: int}
     */
    public function toggleSave(string $accountId, string $campaignId): array
    {
        return $this->toggle($accountId, $campaignId, FeedAdInteraction::TYPE_SAVE);
    }

    public function recordShare(string $accountId, string $campaignId): int
    {
        FeedAdInteraction::query()->create([
            'account_id' => $accountId,
            'campaign_id' => $campaignId,
            'type' => FeedAdInteraction::TYPE_SHARE,
        ]);

        return $this->count($campaignId, FeedAdInteraction::TYPE_SHARE);
    }

    /**
     * @return array{likes: int, saves: int, shares: int, comments: int, liked_by_me: bool, saved_by_me: bool}
     */
    public function counts(string $accountId, string $campaignId): array
    {
        return [
            'likes' => $this->count($campaignId, FeedAdInteraction::TYPE_LIKE),
            'saves' => $this->count($campaignId, FeedAdInteraction::TYPE_SAVE),
            'shares' => $this->count($campaignId, FeedAdInteraction::TYPE_SHARE),
            'comments' => FeedAdComment::query()->where('campaign_id', $campaignId)->count(),
            'liked_by_me' => $this->exists($accountId, $campaignId, FeedAdInteraction::TYPE_LIKE),
            'saved_by_me' => $this->exists($accountId, $campaignId, FeedAdInteraction::TYPE_SAVE),
        ];
    }

    public function addComment(string $accountId, string $campaignId, string $body): FeedAdComment
    {
        return FeedAdComment::query()->create([
            'account_id' => $accountId,
            'campaign_id' => $campaignId,
            'body' => $body,
        ]);
    }

    /**
     * @return Collection<int, FeedAdComment>
     */
    public function listComments(string $campaignId): Collection
    {
        return FeedAdComment::query()
            ->where('campaign_id', $campaignId)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
    }

    /**
     * @return array{active: bool, count: int}
     */
    private function toggle(string $accountId, string $campaignId, string $type): array
    {
        $existing = FeedAdInteraction::query()
            ->where('account_id', $accountId)
            ->where('campaign_id', $campaignId)
            ->where('type', $type)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return ['active' => false, 'count' => $this->count($campaignId, $type)];
        }

        FeedAdInteraction::query()->create(['account_id' => $accountId, 'campaign_id' => $campaignId, 'type' => $type]);

        return ['active' => true, 'count' => $this->count($campaignId, $type)];
    }

    private function count(string $campaignId, string $type): int
    {
        return FeedAdInteraction::query()->where('campaign_id', $campaignId)->where('type', $type)->count();
    }

    private function exists(string $accountId, string $campaignId, string $type): bool
    {
        return FeedAdInteraction::query()
            ->where('account_id', $accountId)
            ->where('campaign_id', $campaignId)
            ->where('type', $type)
            ->exists();
    }
}
