<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Http\Controllers\Admin;

use App\Modules\SmartProfile\Application\Services\ConsentPurposeNotFoundException;
use App\Modules\SmartProfile\Application\Services\ConsentPurposeService;
use App\Modules\SmartProfile\Application\Services\ConsentPurposeVersionNotDraftException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * docs/17 §12/§17 : registre des finalités, texte versionné, publication
 * qui peut exiger une nouvelle décision.
 */
final class ConsentPurposesController extends Controller
{
    public function __construct(private readonly ConsentPurposeService $purposes) {}

    public function index(): JsonResponse
    {
        return response()->json(['purposes' => $this->purposes->listAll()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'requires_new_decision' => ['sometimes', 'boolean'],
        ]);

        $version = $this->purposes->createDraftVersion($data);

        return response()->json(['version' => $version->load('purpose')], 201);
    }

    public function update(Request $request, string $version): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'requires_new_decision' => ['sometimes', 'boolean'],
        ]);

        try {
            $updated = $this->purposes->updateDraftVersion($version, $data);
        } catch (ConsentPurposeNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (ConsentPurposeVersionNotDraftException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['version' => $updated]);
    }

    public function publish(string $version): JsonResponse
    {
        try {
            $published = $this->purposes->publish($version);
        } catch (ConsentPurposeNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }

        return response()->json(['version' => $published]);
    }

    public function suspend(string $purpose): JsonResponse
    {
        try {
            $updated = $this->purposes->suspend($purpose);
        } catch (ConsentPurposeNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }

        return response()->json(['purpose' => $updated]);
    }
}
