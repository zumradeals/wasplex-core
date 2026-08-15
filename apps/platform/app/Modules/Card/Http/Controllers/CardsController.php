<?php

declare(strict_types=1);

namespace App\Modules\Card\Http\Controllers;

use App\Modules\Card\Application\Services\CardIssuer;
use App\Modules\Card\Application\Services\CardOverviewService;
use App\Modules\Card\Application\Services\CardPresenter;
use App\Modules\Card\Application\Services\CardQrService;
use App\Modules\Card\Application\Services\CardSuspensionService;
use App\Modules\Card\Infrastructure\Models\Card;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CardsController
{
    public function __construct(
        private readonly CardOverviewService $overview,
        private readonly CardIssuer $issuer,
        private readonly CardQrService $qr,
        private readonly CardSuspensionService $suspension,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->overview->get($request->user()));
    }

    public function store(Request $request): JsonResponse
    {
        $card = $this->issuer->issueBase($request->user());

        return response()->json(['card' => CardPresenter::card($card)], 201);
    }

    public function qr(Request $request, Card $card): JsonResponse
    {
        return response()->json(['qr' => $this->qr->generateIdentityQr($card, (string) $request->user()->id)]);
    }

    public function receiveQr(Request $request, Card $card): JsonResponse
    {
        $data = $request->validate([
            'amount_minor' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:180'],
        ]);

        return response()->json([
            'qr' => $this->qr->generateReceiveQr(
                $card,
                (string) $request->user()->id,
                isset($data['amount_minor']) ? (int) $data['amount_minor'] : null,
                isset($data['note']) ? (string) $data['note'] : null,
            ),
        ]);
    }

    public function suspend(Request $request, Card $card): JsonResponse
    {
        $card = $this->suspension->suspend($card, (string) $request->user()->id);

        return response()->json(['card' => CardPresenter::card($card)]);
    }
}
