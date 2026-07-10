<?php

use App\Http\Controllers\FleetSbcController;
use Illuminate\Support\Facades\Route;

Route::middleware(['fleet.token'])->prefix('fleet')->group(function () {
    Route::get('health', [FleetSbcController::class, 'health']);
    Route::post('preflight', [FleetSbcController::class, 'preflight']);
    Route::post('repoint', [FleetSbcController::class, 'repoint']);
    Route::post('rollback-repoint', [FleetSbcController::class, 'rollbackRepoint']);
});
