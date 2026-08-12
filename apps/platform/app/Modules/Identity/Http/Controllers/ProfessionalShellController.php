<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class ProfessionalShellController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Identity/ProfessionalShell');
    }
}
