<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class AdminShellController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Identity/AdminShell');
    }

    public function mfaChallenge(): Response
    {
        return Inertia::render('Identity/AdminMfaChallenge');
    }
}
