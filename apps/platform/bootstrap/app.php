<?php

use App\Http\Middleware\AssignTraceId;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Shared\Http\ApiErrorResponse;
use App\Shared\Http\AppException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AssignTraceId::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [HandleInertiaRequests::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (AppException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiErrorResponse::make($request, $e->errorCode, $e->getMessage(), $e->details, $e->status);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiErrorResponse::make($request, 'UNAUTHENTICATED', 'Authentification requise.', status: 401);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiErrorResponse::make(
                    $request,
                    'VALIDATION_FAILED',
                    'La requête contient des données invalides.',
                    ['errors' => $e->errors()],
                    $e->status,
                );
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiErrorResponse::make(
                    $request,
                    'HTTP_'.$e->getStatusCode(),
                    $e->getMessage() ?: 'Erreur inattendue.',
                    [],
                    $e->getStatusCode(),
                );
            }
        });
    })->create();
