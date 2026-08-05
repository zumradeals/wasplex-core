<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Http\Controllers\Admin;

use App\Modules\Ledger\Application\Services\LedgerAuditLogger;
use App\Modules\Ledger\Application\Services\LedgerQueryService;
use App\Modules\Ledger\Application\Services\LedgerTransactionNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class LedgerTransactionsController extends Controller
{
    public function __construct(
        private readonly LedgerQueryService $query,
        private readonly LedgerAuditLogger $auditLogger,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->query->listTransactions();

        $this->auditLogger->record('LedgerConsultationViewed', [
            'actor_account_id' => $request->user()?->id,
            'resource' => 'transactions.index',
        ], $request);

        return response()->json([
            'transactions' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(Request $request, string $transaction): JsonResponse
    {
        $found = $this->query->findTransaction($transaction);

        if ($found === null) {
            throw new LedgerTransactionNotFoundException($transaction);
        }

        $this->auditLogger->record('LedgerConsultationViewed', [
            'actor_account_id' => $request->user()?->id,
            'resource' => 'transactions.show',
            'transaction_id' => $transaction,
        ], $request);

        return response()->json(['transaction' => $found]);
    }
}
