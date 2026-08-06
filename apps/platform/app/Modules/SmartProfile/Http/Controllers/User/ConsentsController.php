<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Http\Controllers\User;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\SmartProfile\Application\Services\ConsentPurposeNotFoundException;
use App\Modules\SmartProfile\Application\Services\ConsentPurposeNotPublishedException;
use App\Modules\SmartProfile\Application\Services\ConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * docs/17-donnees-permissions-consentements-techniques-wasplex.md §1346-1349.
 */
final class ConsentsController extends Controller
{
    public function __construct(private readonly ConsentService $consents) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        return response()->json(['consents' => $this->consents->listForAccount($account->id)]);
    }

    public function grant(Request $request, string $purpose): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        try {
            $consent = $this->consents->grant($account->id, $purpose);
        } catch (ConsentPurposeNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (ConsentPurposeNotPublishedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['consent' => $consent]);
    }

    public function withdraw(Request $request, string $purpose): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        try {
            $this->consents->withdraw($account->id, $purpose);
        } catch (ConsentPurposeNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }

        return response()->json(['message' => 'Consentement retiré.']);
    }

    public function history(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        return response()->json(['history' => $this->consents->history($account->id)]);
    }
}
