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

final class ProfileController extends Controller
{
    public function __construct(private readonly ProfileAnswerService $answers) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        return response()->json([
            'country_code' => $account->country_code,
            'categories' => $this->answers->viewForAccount($account->id),
        ]);
    }

    public function updateCountry(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        $data = $request->validate([
            'country_code' => ['required', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
        ]);

        $account->update(['country_code' => strtoupper($data['country_code'])]);

        return response()->json(['country_code' => $account->country_code]);
    }

    public function declare(Request $request, string $taxonomy): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        $data = $request->validate(['answer' => ['sometimes', 'boolean']]);

        try {
            $answer = $this->answers->answer($account->id, $taxonomy, $data['answer'] ?? true);
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
