<?php

use App\Http\Controllers\Api\InternalSchedulerController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MarketplaceController;
use App\Http\Controllers\Api\V1\WorkspaceApiController;
use Illuminate\Support\Facades\Route;

/*
 | Scheduler trigger for hosts without cron. Deliberately outside /v1: it is not
 | part of the public API surface. Authenticated by the X-Cron-Token header and
 | throttled, and it 404s unless CRON_TOKEN is configured.
 */
Route::post('internal/scheduler/run', [InternalSchedulerController::class, 'run'])
    ->middleware('throttle:scheduler');

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::get('creators', [MarketplaceController::class, 'creators']);

    Route::get('editors', [MarketplaceController::class, 'editors']);
    Route::get('campaigns', [MarketplaceController::class, 'campaigns']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('payments/create', [MarketplaceController::class, 'createPayment']);
        Route::get('payments/{uuid}', [WorkspaceApiController::class, 'paymentStatus']);
        Route::post('campaigns/{campaign}/apply', [MarketplaceController::class, 'apply']);
        Route::get('applications', [WorkspaceApiController::class, 'applications']);

        Route::get('projects', [WorkspaceApiController::class, 'projects']);
        Route::post('projects', [MarketplaceController::class, 'storeProject']);
        Route::get('projects/{project}', [WorkspaceApiController::class, 'project']);
        Route::post('projects/{project}/transition', [WorkspaceApiController::class, 'transitionProject']);

        Route::get('earnings', [WorkspaceApiController::class, 'earnings']);
        Route::post('withdrawals', [MarketplaceController::class, 'withdraw']);
        Route::get('invoices', [WorkspaceApiController::class, 'invoices']);
        Route::get('managers', [WorkspaceApiController::class, 'managers']);
        Route::get('instagram', [WorkspaceApiController::class, 'instagram']);

        Route::get('inbox', [MarketplaceController::class, 'inbox']);
        Route::get('conversations/{uuid}/messages', [MarketplaceController::class, 'messages']);
        Route::post('conversations/{uuid}/messages', [WorkspaceApiController::class, 'postMessage']);

        Route::post('devices', [WorkspaceApiController::class, 'registerDevice']);
    });
});
