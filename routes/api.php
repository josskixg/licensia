<?php

declare(strict_types=1);

use App\Http\Controllers\LicenseServerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Licensia API Routes (Laravel 11)
|--------------------------------------------------------------------------
| Prefix: /api/v1/licenses
*/

Route::prefix('v1/licenses')->group(function (): void {
    Route::post('/issue', [LicenseServerController::class, 'issue'])->name('licensia.issue');
    Route::post('/activate', [LicenseServerController::class, 'activateSeat'])->name('licensia.activate');
    Route::post('/verify', [LicenseServerController::class, 'verifyHeartbeat'])->name('licensia.verify');
    Route::post('/revoke', [LicenseServerController::class, 'revoke'])->name('licensia.revoke');
});
