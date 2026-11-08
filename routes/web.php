<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\LinkPage\Http\Controllers\LinkPageController;

/*
|--------------------------------------------------------------------------
| Web routes of the link-page module
|--------------------------------------------------------------------------
|
| The module loader already applies the "web" middleware.
|
*/

Route::get('enlaces', LinkPageController::class)->name('link_page.show');
