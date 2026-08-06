<?php

declare(strict_types=1);

namespace App\Modules\Matching\Http\Controllers\Admin;

use App\Modules\Matching\Application\Services\MatchingAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class MatchingAuditController extends Controller
{
    public function __construct(private readonly MatchingAuditService $audit) {}

    public function index(): JsonResponse
    {
        return response()->json(['decision_counts' => $this->audit->decisionCounts()]);
    }
}
