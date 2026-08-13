<?php

declare(strict_types=1);

namespace App\Modules\Card\Http\Controllers;

use App\Modules\Card\Application\Services\CardQrService;
use App\Modules\Card\Infrastructure\Models\CardQrToken;
use Illuminate\Http\JsonResponse;

final class CardIdentityQrController
{
    public function __construct(private readonly CardQrService $qr) {}

    public function resolve(CardQrToken $token): JsonResponse
    {
        return response()->json($this->qr->resolve($token));
    }
}
