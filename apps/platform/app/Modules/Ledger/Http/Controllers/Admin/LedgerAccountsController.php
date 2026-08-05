<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Http\Controllers\Admin;

use App\Modules\Ledger\Application\Services\LedgerAuditLogger;
use App\Modules\Ledger\Application\Services\LedgerQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class LedgerAccountsController extends Controller
{
    public function __construct(
        private readonly LedgerQueryService $query,
        private readonly LedgerAuditLogger $auditLogger,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->query->listAccounts();

        $this->auditLogger->record('LedgerConsultationViewed', [
            'actor_account_id' => $request->user()?->id,
            'resource' => 'accounts.index',
        ], $request);

        return response()->json([
            'accounts' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
