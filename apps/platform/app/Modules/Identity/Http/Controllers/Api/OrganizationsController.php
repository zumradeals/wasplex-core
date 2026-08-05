<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\Services\OrganizationRegistrationService;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class OrganizationsController extends Controller
{
    public function __construct(private readonly OrganizationRegistrationService $registration) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        $organizations = $account->organizationMemberships()
            ->where('status', 'active')
            ->with('organization')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->organization->id,
                'name' => $m->organization->name,
                'type' => $m->organization->type,
                'title' => $m->title,
            ]);

        return new JsonResponse(['organizations' => $organizations->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'type' => ['required', Rule::in(['advertiser'])],
            'country_code' => ['required', 'string', 'size:2'],
        ]);

        /** @var Account $account */
        $account = $request->user();

        $organization = $this->registration->register(
            $account,
            $data['name'],
            $data['type'],
            strtoupper($data['country_code']),
        );

        return new JsonResponse(['id' => $organization->id], 201);
    }

    public function show(Request $request, Organization $organization): JsonResponse
    {
        return new JsonResponse([
            'id' => $organization->id,
            'name' => $organization->name,
            'type' => $organization->type,
            'status' => $organization->status,
        ]);
    }
}
