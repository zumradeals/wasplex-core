<?php

declare(strict_types=1);

namespace App\Modules\Feed\Http\Controllers\User;

use App\Modules\Feed\Application\Services\AttentionNotQualifiedException;
use App\Modules\Feed\Application\Services\AttentionService;
use App\Modules\Feed\Application\Services\DeliveryNotStartableException;
use App\Modules\Feed\Application\Services\FeedDeliveryNotFoundException;
use App\Modules\Feed\Application\Services\FeedInteractionService;
use App\Modules\Feed\Infrastructure\Models\FeedAdDelivery;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Subscriptions\Application\Services\QuotaExhaustedException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * docs/08-feed-principal-wasplex.md §67 : API Feed. Le client ne crédite
 * jamais localement — chaque transition passe par le serveur
 * (docs/chantiers/P009-CHANTIER.md §1).
 */
final class FeedDeliveriesController extends Controller
{
    public function __construct(
        private readonly AttentionService $attention,
        private readonly FeedInteractionService $interactions,
    ) {}

    public function next(Request $request): JsonResponse
    {
        $data = $request->validate(['feed_session_id' => ['required', 'string']]);

        /** @var Account $account */
        $account = $request->user();

        $result = $this->attention->next($data['feed_session_id'], $account->id);

        if ($result === null) {
            return response()->json(['delivery' => null]);
        }

        return response()->json(['delivery' => $this->format($result, $account->id)]);
    }

    public function start(Request $request, string $delivery): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        try {
            $updated = $this->attention->start($delivery, $account->id);
        } catch (FeedDeliveryNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (DeliveryNotStartableException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (QuotaExhaustedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['delivery' => $updated]);
    }

    public function heartbeat(Request $request, string $delivery): JsonResponse
    {
        $data = $request->validate(['visible_duration_ms' => ['required', 'integer', 'min:0']]);

        /** @var Account $account */
        $account = $request->user();

        try {
            $updated = $this->attention->heartbeat($delivery, $account->id, $data['visible_duration_ms']);
        } catch (FeedDeliveryNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }

        return response()->json(['delivery' => $updated]);
    }

    public function ended(Request $request, string $delivery): JsonResponse
    {
        $data = $request->validate(['visible_duration_ms' => ['required', 'integer', 'min:0']]);

        /** @var Account $account */
        $account = $request->user();

        $current = FeedAdDelivery::query()
            ->whereKey($delivery)
            ->where('account_id', $account->id)
            ->first();

        if ($current !== null
            && $current->status === FeedAdDelivery::STATUS_STARTED
            && $current->requires_media_end) {
            $toleranceMs = max(0, (int) config('feed.media_end_tolerance_ms'));

            if ($current->last_heartbeat_at === null
                || $current->visible_duration_ms + $toleranceMs < $current->required_duration_ms) {
                return response()->json([
                    'message' => 'La fin de la vidéo ne peut être validée sans preuve d’attention suffisante.',
                ], 422);
            }
        }

        try {
            $updated = $this->attention->markMediaEnded($delivery, $account->id, $data['visible_duration_ms']);
        } catch (FeedDeliveryNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (AttentionNotQualifiedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['delivery' => $updated]);
    }

    public function complete(Request $request, string $delivery): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        try {
            $result = $this->attention->complete($delivery, $account->id);
        } catch (FeedDeliveryNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (AttentionNotQualifiedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'delivery' => $result['delivery'],
            'gain_minor' => $result['gain_minor'],
            'balance_minor' => $result['balance_minor'],
        ]);
    }

    public function abandon(Request $request, string $delivery): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        try {
            $updated = $this->attention->abandon($delivery, $account->id);
        } catch (FeedDeliveryNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }

        return response()->json(['delivery' => $updated]);
    }

    public function why(Request $request, string $delivery): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        try {
            $explanation = $this->attention->explain($delivery, $account->id);
        } catch (FeedDeliveryNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }

        return response()->json(['explanation' => $explanation]);
    }

    public function like(Request $request, string $campaign): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        return response()->json($this->interactions->toggleLike($account->id, $campaign));
    }

    public function save(Request $request, string $campaign): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        return response()->json($this->interactions->toggleSave($account->id, $campaign));
    }

    public function share(Request $request, string $campaign): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        return response()->json(['count' => $this->interactions->recordShare($account->id, $campaign)]);
    }

    public function comments(string $campaign): JsonResponse
    {
        return response()->json(['comments' => $this->interactions->listComments($campaign)]);
    }

    public function storeComment(Request $request, string $campaign): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:500']]);

        /** @var Account $account */
        $account = $request->user();

        $comment = $this->interactions->addComment($account->id, $campaign, $data['body']);

        return response()->json(['comment' => $comment], 201);
    }

    /**
     * @param  array{delivery: mixed, brand_name: ?string, objective_code: ?string, cta_label: ?string}  $result
     */
    private function format(array $result, string $accountId): array
    {
        $delivery = $result['delivery'];

        return array_merge($delivery->toArray(), [
            'brand_name' => $result['brand_name'],
            'objective_code' => $result['objective_code'],
            'cta_label' => $result['cta_label'],
            'creative' => $result['creative'],
            'interactions' => $this->interactions->counts($accountId, $delivery->campaign_id),
        ]);
    }
}
