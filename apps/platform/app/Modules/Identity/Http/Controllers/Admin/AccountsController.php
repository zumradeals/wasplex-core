<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\Services\AccountDirectoryService;
use App\Modules\Identity\Application\Services\AuditLogger;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Docs/chantiers/P017-CHANTIER.md (écran Utilisateurs) : première surface
 * admin de lecture sur un compte quelconque. Ne remonte que des champs
 * réels (identifiants, statut, adhésions) — aucune donnée sensible
 * (KYC, historique détaillé) n'est fabriquée quand elle n'existe pas.
 */
final class AccountsController extends Controller
{
    public function __construct(
        private readonly AccountDirectoryService $directory,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $accounts = $this->directory->search($request->query('q'));

        return new JsonResponse([
            'accounts' => $accounts->map(fn (Account $account) => [
                'id' => $account->id,
                'status' => $account->status,
                'is_restricted' => $account->isRestricted(),
                'primary_identifier' => $account->primaryIdentifierLabel(),
                'created_at' => $account->created_at->toIso8601String(),
            ])->values(),
        ]);
    }

    public function show(Request $request, string $account): JsonResponse
    {
        $detail = $this->directory->detail($account);

        $this->auditLogger->record('AccountViewed', [
            'actor_account_id' => $request->user()?->id,
            'resource_type' => 'account',
            'resource_id' => $account,
        ], $request);

        return new JsonResponse([
            'account' => [
                'id' => $detail['account']->id,
                'status' => $detail['account']->status,
                'country_code' => $detail['account']->country_code,
                'is_restricted' => $detail['account']->isRestricted(),
                'created_at' => $detail['account']->created_at->toIso8601String(),
                'identifiers' => $detail['account']->identifiers->pluck('value'),
            ],
            'is_online' => $detail['is_online'],
            'wallet_balance_minor' => $detail['wallet_balance_minor'],
            'economic_class_code' => $detail['economic_class_code'],
            'advertiser_organization_name' => $detail['advertiser_organization_name'],
            'organizations' => $detail['organizations'],
        ]);
    }

    public function restrict(Request $request, string $account): JsonResponse
    {
        $updated = $this->directory->restrict($account);

        $this->auditLogger->record('AccountRestricted', [
            'actor_account_id' => $request->user()?->id,
            'resource_type' => 'account',
            'resource_id' => $account,
        ], $request);

        return new JsonResponse(['account' => ['id' => $updated->id, 'is_restricted' => $updated->isRestricted()]]);
    }

    public function unrestrict(Request $request, string $account): JsonResponse
    {
        $updated = $this->directory->unrestrict($account);

        $this->auditLogger->record('AccountUnrestricted', [
            'actor_account_id' => $request->user()?->id,
            'resource_type' => 'account',
            'resource_id' => $account,
        ], $request);

        return new JsonResponse(['account' => ['id' => $updated->id, 'is_restricted' => $updated->isRestricted()]]);
    }
}
