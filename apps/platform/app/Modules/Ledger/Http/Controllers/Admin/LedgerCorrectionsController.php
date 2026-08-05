<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Http\Controllers\Admin;

use App\Modules\Ledger\Application\Services\LedgerCompensationContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class LedgerCorrectionsController extends Controller
{
    public function __construct(private readonly LedgerCompensationContract $compensation) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'original_transaction_id' => ['required', 'string'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $pending = $this->compensation->proposeReversal(
            $data['original_transaction_id'],
            $data['reason'],
            (string) $request->user()->id,
        );

        return response()->json(['transaction' => $pending], 201);
    }

    public function approve(Request $request, string $transaction): JsonResponse
    {
        $posted = $this->compensation->approve($transaction, (string) $request->user()->id);

        return response()->json(['transaction' => $posted]);
    }

    public function reject(Request $request, string $transaction): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $rejected = $this->compensation->reject($transaction, (string) $request->user()->id, $data['reason']);

        return response()->json(['transaction' => $rejected]);
    }
}
