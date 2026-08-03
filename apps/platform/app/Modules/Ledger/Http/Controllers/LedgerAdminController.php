<?php

namespace App\Modules\Ledger\Http\Controllers;

use App\Modules\Identity\Application\Services\AccessAuditor;
use App\Modules\Ledger\Application\Contracts\LedgerQueryContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class LedgerAdminController
{
    public function __construct(
        private LedgerQueryContract $ledger,
        private AccessAuditor $auditor,
    ) {}

    public function transactions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_module' => ['nullable', 'string', 'max:80'],
            'type' => ['nullable', 'string', 'max:80'],
            'currency' => ['nullable', 'string', 'max:12'],
            'business_reference' => ['nullable', 'string', 'max:160'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $filters = array_filter([
            'source_module' => $validated['source_module'] ?? null,
            'type' => $validated['type'] ?? null,
            'currency' => $validated['currency'] ?? null,
            'business_reference' => $validated['business_reference'] ?? null,
        ], 'is_string');

        $this->auditor->record(
            $request,
            'ledger.transactions.viewed',
            'success',
            'wallet.ledger.view',
            ['filters' => $filters],
        );

        return response()->json([
            'data' => $this->ledger->transactions(
                $filters,
                (int) ($validated['per_page'] ?? 25),
            ),
        ]);
    }

    public function transaction(Request $request, string $transactionId): JsonResponse
    {
        $transaction = $this->ledger->transaction($transactionId);
        abort_if($transaction === null, 404, 'Transaction comptable introuvable.');

        $this->auditor->record(
            $request,
            'ledger.transaction.viewed',
            'success',
            'wallet.audit.view',
            ['transaction_id' => $transactionId],
        );

        return response()->json(['data' => $transaction]);
    }

    public function accounts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $this->auditor->record(
            $request,
            'ledger.accounts.viewed',
            'success',
            'wallet.ledger.view',
        );

        return response()->json([
            'data' => $this->ledger->accounts((int) ($validated['per_page'] ?? 25)),
        ]);
    }
}
