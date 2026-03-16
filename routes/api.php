<?php

use App\Http\Controllers\Api\AuditEventController;
use App\Http\Controllers\Api\SecurityEventController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Security Audit API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1/security')->group(function () {
    Route::post('/events', [SecurityEventController::class, 'publish'])->name('security.events.publish');
    Route::get('/audit', [AuditEventController::class, 'index'])->name('security.audit.index');
    Route::get('/audit/stats', [AuditEventController::class, 'stats'])->name('security.audit.stats');
    Route::get('/audit/{eventId}', [AuditEventController::class, 'show'])->name('security.audit.show');
});
