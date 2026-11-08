<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

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
        // Route::apiResource('items', ItemController::class);
    });
