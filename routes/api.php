<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\LinkPage\Http\Controllers\StatsBeaconController;

/*
|--------------------------------------------------------------------------
| API routes of the link-page module
|--------------------------------------------------------------------------
|
| The module loader already applies the "api" middleware and the "/api"
| prefix: these routes answer under /api/link-page/...
|
*/

Route::prefix('link-page')
    ->name('link_page.api.')
    ->group(function (): void {
        // Anonymous counters of the link page; the limiter is registered by the provider
        Route::post('stats', StatsBeaconController::class)
            ->middleware('throttle:link-page-stats')
            ->name('stats');
    });
