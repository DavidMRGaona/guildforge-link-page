<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes of the link-page module
|--------------------------------------------------------------------------
|
| The module loader already applies the "web" middleware. Public URL
| segments are shown to visitors: translate them to Spanish (e.g. "mesas").
|
*/

Route::prefix('link-page')
    ->name('link_page.')
    ->group(function (): void {
        // Route::get('items', [ItemController::class, 'index'])->name('items.index');
    });
