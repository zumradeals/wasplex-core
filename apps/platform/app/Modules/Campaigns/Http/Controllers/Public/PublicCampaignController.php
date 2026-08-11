<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Http\Controllers\Public;

use App\Modules\Campaigns\Application\Services\PublicCampaignService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class PublicCampaignController extends Controller
{
    public function __construct(private readonly PublicCampaignService $campaigns) {}

    public function show(Request $request, string $slug): Response
    {
        $campaign = $this->campaigns->presentation($slug);

        abort_if($campaign === null, 404);

        return Inertia::render('Campaigns/PublicCampaign', [
            'campaign' => $campaign,
            'authenticated' => Auth::check(),
        ]);
    }
}
