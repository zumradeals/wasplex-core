<?php

declare(strict_types=1);

namespace App\Modules\Card\Http\Controllers;

use App\Modules\Card\Application\Services\CardQrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CardQrCheckController
{
    public function __construct(private readonly CardQrService $qr) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:80']]);

        return response()->json($this->qr->resolve($data['token']));
    }
}
