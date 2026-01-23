<?php

use App\Http\Controllers\DispatcherController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// Routes for managing dispatcher destinations from modal
Route::post('/admin/dispatchers', [DispatcherController::class, 'store'])
    ->name('admin.dispatchers.store')
    ->middleware(['web', 'auth']);

Route::put('/admin/dispatchers/{dispatcher}', [DispatcherController::class, 'update'])
    ->name('admin.dispatchers.update')
    ->middleware(['web', 'auth']);

Route::delete('/admin/dispatchers/{dispatcher}', [DispatcherController::class, 'destroy'])
    ->name('admin.dispatchers.destroy')
    ->middleware(['web', 'auth']);
