<?php

use App\Http\Controllers\FleetSbcController;
use Illuminate\Support\Facades\Route;

Route::middleware(['fleet.token'])->prefix('fleet')->group(function () {
    Route::get('health', [FleetSbcController::class, 'health']);
    Route::get('domains', [FleetSbcController::class, 'listDomains']);
    Route::get('dispatcher-sets', [FleetSbcController::class, 'listDispatcherSets']);
    Route::post('preflight', [FleetSbcController::class, 'preflight']);
    Route::post('repoint', [FleetSbcController::class, 'repoint']);
    Route::post('rollback-repoint', [FleetSbcController::class, 'rollbackRepoint']);
    Route::post('project-dids', [FleetSbcController::class, 'projectDids']);
    Route::post('domains', [FleetSbcController::class, 'registerDomain']);
    Route::post('provision-node', [FleetSbcController::class, 'provisionNode']);
    Route::post('backup', [FleetSbcController::class, 'backup']);
    Route::post('warm-pull', [FleetSbcController::class, 'warmPull']);
    Route::post('le-setup', [FleetSbcController::class, 'leSetup']);
});
