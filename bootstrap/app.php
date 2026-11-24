<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (UnauthorizedException $e, Request $request) {
            $message = match ($e->getMessage()) {
                '', 'User does not have the right roles.' => 'Anda tidak memiliki peran yang sesuai untuk mengakses fitur ini.',
                'User does not have the right permissions.' => 'Anda tidak memiliki izin untuk melakukan tindakan ini.',
                'User does not have any of the necessary access rights.' => 'Anda tidak memiliki hak akses yang diperlukan.',
                'User is not logged in.' => 'Anda harus masuk terlebih dahulu.',
                default => $e->getMessage(),
            };

            return response()->json([
                'message' => $message,
            ], 403);
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            $message = match ($e->getMessage()) {
                '', 'This action is unauthorized.' => 'Anda tidak memiliki izin untuk melakukan tindakan ini.',
                default => $e->getMessage(),
            };

            return response()->json([
                'message' => $message,
            ], 403);
        });
    })->create();
