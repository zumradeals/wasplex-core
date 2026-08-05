<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class GuestPagesController extends Controller
{
    public function login(): Response
    {
        return Inertia::render('Identity/Login');
    }

    public function register(): Response
    {
        return Inertia::render('Identity/Register');
    }
}
