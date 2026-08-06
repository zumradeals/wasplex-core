<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Http\Controllers\User;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\SmartProfile\Application\Services\ProfileAnswerService;
use App\Modules\SmartProfile\Application\Services\ProfileTaxonomyNotActiveException;
use App\Modules\SmartProfile\Application\Services\ProfileTaxonomyNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * docs/09-compte-universel-et-mon-espace-intelligent-wasplex.md Phase 4 :
 * self-service, aucune capacité spéciale requise au-delà d'une session
 * authentifiée valide (même discipline que docs/03 §24).
 */
final class ProfileController extends Controller
{
    public function __construct(private readonly ProfileAnswerService $answers) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        return response()->json(['categories' => $this->answers->viewForAccount($account->id)]);
    }

    public function declare(Request $request, string $taxonomy): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        try {
            $answer = $this->answers->declare($account->id, $taxonomy);
        } catch (ProfileTaxonomyNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (ProfileTaxonomyNotActiveException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['answer' => $answer], 201);
    }

    public function withdraw(Request $request, string $taxonomy): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        $this->answers->withdraw($account->id, $taxonomy);

        return response()->json(['message' => 'Information retirée.']);
    }
}
