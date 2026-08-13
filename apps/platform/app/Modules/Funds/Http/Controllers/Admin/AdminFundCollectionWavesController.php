<?php

declare(strict_types=1);

namespace App\Modules\Funds\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Funds\Application\Services\FundCollectionWaveService;
use App\Modules\Funds\Infrastructure\Models\FundCollectionSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class AdminFundCollectionWavesController extends Controller
{
    public function __construct(private readonly FundCollectionWaveService $waves) {}

    public function store(Request $request, FundCollectionSnapshot $snapshot): JsonResponse
    {
        try {
            $wave = $this->waves->createSecondWave($snapshot, (string) $request->user()->id);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $wave], 201);
    }

    public function execute(Request $request, FundCollectionSnapshot $snapshot, int $waveNumber): JsonResponse
    {
        try {
            $wave = $this->waves->executeWave($snapshot, $waveNumber, (string) $request->user()->id);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $wave]);
    }
}
