<?php

use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Admin\ServiceRequestController as AdminServiceRequestController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\User\ServiceCatalogController;
use App\Http\Controllers\User\ServiceRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:20,1')->name('login.attempt');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/services', [ServiceCatalogController::class, 'index'])->name('services.index');
    Route::get('/services/{service}', [ServiceCatalogController::class, 'show'])->name('services.show');
    Route::post('/services/{service}/requests', [ServiceRequestController::class, 'store'])->middleware('throttle:240,1')->name('requests.store');

    Route::get('/requests', [ServiceRequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/{request}', [ServiceRequestController::class, 'show'])->name('requests.show');
    Route::get('/requests/{request}/attempts/{attempt}', [ServiceRequestController::class, 'attempt'])->name('requests.attempts.show');
    Route::put('/requests/{request}', [ServiceRequestController::class, 'update'])->middleware('throttle:120,1')->name('requests.update');
    Route::post('/requests/{request}/refresh', [ServiceRequestController::class, 'refresh'])->middleware('throttle:120,1')->name('requests.refresh');

    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function (): void {
        Route::resource('users', AdminUserController::class)->except(['show']);
        Route::resource('services', AdminServiceController::class)->except(['show']);
        Route::get('requests', [AdminServiceRequestController::class, 'index'])->name('requests.index');
        Route::get('requests/{request}', [AdminServiceRequestController::class, 'show'])->name('requests.show');
        Route::get('requests/{request}/attempts/{attempt}', [AdminServiceRequestController::class, 'attempt'])->name('requests.attempts.show');
    });
});
