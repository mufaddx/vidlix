<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureFeature;
use App\Http\Middleware\EnsureWorkspace;
use App\Http\Middleware\MaintenanceGate;
use App\Http\Middleware\ResolveCustomDomain;
use App\Http\Middleware\RouteByHost;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyTurnstile;
use App\Support\RequestId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
        $middleware->append(AssignRequestId::class);
        $middleware->append(SecurityHeaders::class);
        // Before the maintenance gate and before routing: a request on somebody
        // else's hostname must be narrowed to their contact form, or refused,
        // before anything downstream gets a chance to serve it something wider.
        $middleware->append(ResolveCustomDomain::class);
        // After the tenant check, before anything else: each of the four hosts
        // serves only its own part of the product, or the four domains are just
        // four names for one website.
        $middleware->append(RouteByHost::class);
        // Runs after the security headers so a closed site still sends them.
        $middleware->append(MaintenanceGate::class);
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'workspace' => EnsureWorkspace::class,
            'feature' => EnsureFeature::class,
            'turnstile' => VerifyTurnstile::class,
        ]);
        /*
         | The admin panel is a separate front door. A guest who opens an admin
         | URL must land on the staff sign-in, not the member one — otherwise
         | signing in as a member looks like the way into the panel.
         */
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('admin') || $request->is('admin/*')
                ? route('admin.login')
                : route('login'),
        );
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'code' => 'VALIDATION_ERROR',
                    'errors' => $e->errors(),
                    'request_id' => RequestId::get(),
                ], 422);
            }

            // A missing or invalid token is a 401, not a server fault.
            $status = match (true) {
                $e instanceof AuthenticationException => 401,
                $e instanceof AuthorizationException => 403,
                $e instanceof ModelNotFoundException => 404,
                $e instanceof HttpExceptionInterface => $e->getStatusCode(),
                default => 500,
            };
            $code = match ($status) {
                401 => 'UNAUTHENTICATED',
                403 => 'RESOURCE_FORBIDDEN',
                404 => 'NOT_FOUND',
                default => 'SERVER_ERROR',
            };

            return response()->json([
                'success' => false,
                'message' => $status === 500 ? __('Something went wrong.') : $e->getMessage(),
                'code' => $code,
                'errors' => new stdClass,
                'request_id' => RequestId::get(),
            ], $status);
        });
    })->create();
