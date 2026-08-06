<?php

declare(strict_types=1);

namespace App\Modules\Reconciliation\Http\Controllers\Admin;

use App\Modules\Reconciliation\Application\Services\ReconciliationService;
use App\Modules\Reconciliation\Infrastructure\Models\ReconciliationEntry;
use App\Modules\Reconciliation\Infrastructure\Models\ReconciliationRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

final class ReconciliationController extends Controller
{
    public function __construct(private readonly ReconciliationService $service) {}

    public function store(Request $request): JsonResponse
    {
        $run = $this->service->run((string) $request->user()->id);

        return response()->json(['run' => $this->formatRun($run)], 201);
    }

    public function runs(): JsonResponse
    {
        $runs = ReconciliationRun::query()->orderByDesc('started_at')->limit(50)->get();

        return response()->json(['runs' => $runs->map(fn (ReconciliationRun $run): array => $this->formatRun($run))]);
    }

    public function entries(Request $request): JsonResponse
    {
        $data = $request->validate([
            'result' => ['nullable', 'string', Rule::in([
                ReconciliationEntry::RESULT_MATCHED, ReconciliationEntry::RESULT_PARTIALLY_MATCHED,
                ReconciliationEntry::RESULT_UNMATCHED, ReconciliationEntry::RESULT_DUPLICATE,
                ReconciliationEntry::RESULT_AMOUNT_MISMATCH, ReconciliationEntry::RESULT_STATUS_MISMATCH,
                ReconciliationEntry::RESULT_MANUAL_REVIEW,
            ])],
        ]);

        $entries = ReconciliationEntry::query()
            ->when($data['result'] ?? null, fn ($query, $result) => $query->where('result_code', $result))
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return response()->json(['entries' => $entries->map(fn (ReconciliationEntry $entry): array => $this->formatEntry($entry))]);
    }

    public function export(Request $request): Response
    {
        $data = $request->validate(['result' => ['nullable', 'string']]);

        $entries = ReconciliationEntry::query()
            ->when($data['result'] ?? null, fn ($query, $result) => $query->where('result_code', $result))
            ->orderByDesc('created_at')
            ->get();

        $header = "source_module,source_record_id,provider_reference,amount_minor,currency,internal_status,provider_status_raw,result_code,notes,created_at\n";
        $rows = $entries->map(function (ReconciliationEntry $entry): string {
            $fields = [
                $entry->source_module, $entry->source_record_id, $entry->provider_reference ?? '',
                $entry->amount_minor ?? '', $entry->currency ?? '', $entry->internal_status,
                $entry->provider_status_raw ?? '', $entry->result_code,
                str_replace(["\r", "\n", ','], ' ', (string) $entry->notes), $entry->created_at?->toIso8601String(),
            ];

            return implode(',', $fields);
        })->implode("\n");

        return response($header.$rows."\n", 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="reconciliation-export.csv"',
        ]);
    }

    private function formatRun(ReconciliationRun $run): array
    {
        return [
            'id' => $run->id,
            'triggered_by' => $run->triggered_by,
            'started_at' => $run->started_at,
            'completed_at' => $run->completed_at,
            'total_checked' => $run->total_checked,
            'summary' => $run->summary,
        ];
    }

    private function formatEntry(ReconciliationEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'run_id' => $entry->run_id,
            'source_module' => $entry->source_module,
            'source_record_id' => $entry->source_record_id,
            'provider_reference' => $entry->provider_reference,
            'amount_minor' => $entry->amount_minor,
            'currency' => $entry->currency,
            'internal_status' => $entry->internal_status,
            'provider_status_raw' => $entry->provider_status_raw,
            'result_code' => $entry->result_code,
            'notes' => $entry->notes,
            'created_at' => $entry->created_at,
        ];
    }
}
