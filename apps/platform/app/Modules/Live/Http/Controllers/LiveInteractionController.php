<?php

declare(strict_types=1);

namespace App\Modules\Live\Http\Controllers;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Live\Application\Services\LiveInteractionPresenter;
use App\Modules\Live\Application\Services\LiveInteractionService;
use App\Modules\Live\Infrastructure\Models\LiveComment;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class LiveInteractionController
{
    public function __construct(private readonly LiveInteractionService $interactions) {}

    public function viewerIndex(Request $request, LiveEvent $live): JsonResponse
    {
        return response()->json($this->interactions->viewerState($live, $request->user()));
    }

    public function hostIndex(Request $request, LiveEvent $live): JsonResponse
    {
        return response()->json($this->interactions->hostState(
            $live,
            $request->user(),
            $this->advertiserOrganizationId($request),
        ));
    }

    public function viewerComment(Request $request, LiveEvent $live): JsonResponse
    {
        $validated = $this->commentPayload($request);
        $comment = $this->interactions->commentAsViewer(
            $live,
            $request->user(),
            $validated['body'],
            $validated['parent_comment_id'] ?? null,
        );

        return response()->json(['comment' => LiveInteractionPresenter::comment($comment)], 201);
    }

    public function hostComment(Request $request, LiveEvent $live): JsonResponse
    {
        $validated = $this->commentPayload($request);
        $comment = $this->interactions->commentAsHost(
            $live,
            $request->user(),
            $this->advertiserOrganizationId($request),
            $validated['body'],
            $validated['parent_comment_id'] ?? null,
        );

        return response()->json(['comment' => LiveInteractionPresenter::comment($comment)], 201);
    }

    public function viewerReaction(Request $request, LiveEvent $live): JsonResponse
    {
        $reaction = $this->reaction($request);
        $this->interactions->reactAsViewer($live, $request->user(), $reaction);

        return response()->json(['accepted' => true], 202);
    }

    public function hostReaction(Request $request, LiveEvent $live): JsonResponse
    {
        $reaction = $this->reaction($request);
        $this->interactions->reactAsHost(
            $live,
            $request->user(),
            $this->advertiserOrganizationId($request),
            $reaction,
        );

        return response()->json(['accepted' => true], 202);
    }

    public function report(Request $request, LiveEvent $live, LiveComment $comment): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in(['spam', 'harassment', 'hate', 'violence', 'privacy', 'misinformation', 'other'])],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        $report = $this->interactions->reportComment(
            $live,
            $comment,
            $request->user(),
            $validated['category'],
            $validated['note'] ?? null,
        );

        return response()->json(['reported' => true, 'report_id' => $report->id], 201);
    }

    public function pin(Request $request, LiveEvent $live, LiveComment $comment): JsonResponse
    {
        $comment = $this->interactions->togglePin(
            $live,
            $comment,
            $request->user(),
            $this->advertiserOrganizationId($request),
        );

        return response()->json(['comment' => LiveInteractionPresenter::comment($comment)]);
    }

    public function hide(Request $request, LiveEvent $live, LiveComment $comment): JsonResponse
    {
        $comment = $this->interactions->hideComment(
            $live,
            $comment,
            $request->user(),
            $this->advertiserOrganizationId($request),
        );

        return response()->json(['comment' => LiveInteractionPresenter::comment($comment)]);
    }

    public function settings(Request $request, LiveEvent $live): JsonResponse
    {
        $validated = $request->validate([
            'comments_enabled' => ['sometimes', 'boolean'],
            'slow_mode_seconds' => ['sometimes', 'integer', Rule::in([0, 2, 5, 10])],
        ]);

        return response()->json([
            'settings' => $this->interactions->updateSettings(
                $live,
                $request->user(),
                $this->advertiserOrganizationId($request),
                $validated,
            ),
        ]);
    }

    public function silence(Request $request, LiveEvent $live, Account $account): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $restriction = $this->interactions->silence(
            $live,
            $account,
            $request->user(),
            $this->advertiserOrganizationId($request),
            $validated['reason'] ?? null,
        );

        return response()->json([
            'restriction' => [
                'account_id' => $restriction->account_id,
                'is_active' => $restriction->is_active,
                'reason' => $restriction->reason,
            ],
        ]);
    }

    public function unsilence(Request $request, LiveEvent $live, Account $account): JsonResponse
    {
        $this->interactions->unsilence(
            $live,
            $account,
            $request->user(),
            $this->advertiserOrganizationId($request),
        );

        return response()->json(['unsilenced' => true]);
    }

    /**
     * @return array{body: string, parent_comment_id?: string|null}
     */
    private function commentPayload(Request $request): array
    {
        return $request->validate([
            'body' => ['required', 'string', 'max:300'],
            'parent_comment_id' => ['nullable', 'string'],
        ]);
    }

    private function reaction(Request $request): string
    {
        $validated = $request->validate([
            'reaction' => ['required', Rule::in(['heart', 'clap', 'fire', 'smile'])],
        ]);

        return (string) $validated['reaction'];
    }

    private function advertiserOrganizationId(Request $request): string
    {
        return (string) $request->attributes->get('advertiser_organization_id');
    }
}
