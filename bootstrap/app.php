<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use src\Modules\Shared\Helper\ResponseJsend;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: [
            __DIR__.'/../src/Modules/Identity/Presentation/Routes/api.php',
            __DIR__.'/../src/Modules/Organization/Presentation/Routes/api.php',
            __DIR__.'/../src/Modules/Report/Presentation/Routes/api.php',
            __DIR__.'/../src/Modules/Attachment/Presentation/Routes/api.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $exception): bool => true,
        );

        $exceptions->render(function (AuthenticationException $exception): JsonResponse {
            return (new ResponseJsend(
                status: ResponseJsend::STATUS_ERROR,
                message: 'Não autenticado.',
                code: 401,
            ))->toJsonResponse(401);
        });

        $exceptions->render(function (HttpException $exception): ?JsonResponse {
            if ($exception->getStatusCode() !== 403) {
                return null;
            }

            return (new ResponseJsend(
                status: ResponseJsend::STATUS_ERROR,
                message: 'Acesso não autorizado.',
                code: 403,
            ))->toJsonResponse(403);
        });
    })->create();
