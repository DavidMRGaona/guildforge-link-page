<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\LinkPage\Domain\Enums\PrintPaper;
use Modules\LinkPage\Domain\ValueObjects\ScanCode;
use Modules\LinkPage\Http\Controllers\LinkPageController;
use Modules\LinkPage\Http\Controllers\QrPrintController;
use Modules\LinkPage\Http\Controllers\QrRedirectController;

/*
|--------------------------------------------------------------------------
| Web routes of the link-page module
|--------------------------------------------------------------------------
|
| The module loader already applies the "web" middleware.
|
*/

Route::get('enlaces', LinkPageController::class)->name('link_page.show');

// Printed on posters: the path and the code never change
Route::get('qr/{code}', QrRedirectController::class)
    ->where('code', ScanCode::ROUTE_PATTERN)
    ->name('link_page.qr');

Route::middleware('auth')
    ->get('link-page/qr-codes/{qrCode}/print/{paper}', QrPrintController::class)
    ->whereUuid('qrCode')
    ->whereIn('paper', array_map(fn (PrintPaper $paper): string => $paper->value, PrintPaper::cases()))
    ->name('link_page.qr_codes.print');
