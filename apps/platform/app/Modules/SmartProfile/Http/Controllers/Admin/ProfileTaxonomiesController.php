<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Http\Controllers\Admin;

use App\Modules\SmartProfile\Application\Services\InvalidProfileTaxonomyCategoryException;
use App\Modules\SmartProfile\Application\Services\ProfileTaxonomyNotFoundException;
use App\Modules\SmartProfile\Application\Services\ProfileTaxonomyService;
use App\Modules\SmartProfile\Infrastructure\Models\ProfileTaxonomy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

final class ProfileTaxonomiesController extends Controller
{
    public function __construct(private readonly ProfileTaxonomyService $taxonomies) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'taxonomies' => $this->taxonomies->listAll(),
            'categories' => ProfileTaxonomy::CATEGORIES,
            'input_types' => ProfileTaxonomy::INPUT_TYPES,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:120', 'unique:profile_taxonomies,code'],
            'category' => ['required', 'string'],
            'facet' => ['nullable', 'string', 'max:120'],
            'input_type' => ['sometimes', 'string', Rule::in(ProfileTaxonomy::INPUT_TYPES)],
            'label' => ['required', 'string', 'max:255'],
            'freshness_days' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $taxonomy = $this->taxonomies->create($data);
        } catch (InvalidProfileTaxonomyCategoryException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['taxonomy' => $taxonomy], 201);
    }

    public function update(Request $request, string $taxonomy): JsonResponse
    {
        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:255'],
            'facet' => ['sometimes', 'nullable', 'string', 'max:120'],
            'input_type' => ['sometimes', 'string', Rule::in(ProfileTaxonomy::INPUT_TYPES)],
            'freshness_days' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        try {
            $updated = $this->taxonomies->update($taxonomy, $data);
        } catch (ProfileTaxonomyNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }

        return response()->json(['taxonomy' => $updated]);
    }

    public function activate(string $taxonomy): JsonResponse
    {
        try {
            $updated = $this->taxonomies->activate($taxonomy);
        } catch (ProfileTaxonomyNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }

        return response()->json(['taxonomy' => $updated]);
    }

    public function suspend(string $taxonomy): JsonResponse
    {
        try {
            $updated = $this->taxonomies->suspend($taxonomy);
        } catch (ProfileTaxonomyNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }

        return response()->json(['taxonomy' => $updated]);
    }
}
