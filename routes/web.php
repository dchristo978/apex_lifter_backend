<?php

use App\Http\Controllers\Admin\GymController;
use App\Http\Controllers\Admin\MachineController;
use App\Http\Controllers\Admin\QrController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('machines', MachineController::class)->except('show');
        Route::resource('gyms', GymController::class)->except('show');
        Route::get('gyms/{gym}/qr', [QrController::class, 'gymSheet'])->name('gyms.qr');
    });
});

require __DIR__.'/settings.php';
