<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Server-to-server only: no browser session or CSRF exemption on web routes.
Route::middleware([\App\Http\Middleware\AuthenticateCloudflareCron::class, 'throttle:cloudflare-cron'])->group(function () {
    Route::get('/internal/notifications/status', [\App\Http\Controllers\CloudflareCronController::class, 'status'])->name('cloudflare-cron.status');
    Route::post('/internal/notifications/tick', [\App\Http\Controllers\CloudflareCronController::class, 'tick'])->name('cloudflare-cron.tick');
});
