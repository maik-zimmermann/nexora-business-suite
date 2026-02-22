<?php

use App\Http\Controllers\TenantPickerController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public Routes (root domain only)
|--------------------------------------------------------------------------
*/
Route::middleware('root.domain')->group(function () {
    Route::get('/', function () {
        return Inertia::render('Welcome');
    })->middleware('tenant.redirect')->name('home');

    Route::get('tenants', [TenantPickerController::class, 'show'])
        ->middleware('auth')
        ->name('tenants.show');

    require __DIR__.'/checkout.php';
    require __DIR__.'/onboarding.php';
});

/*
|--------------------------------------------------------------------------
| Tenant Routes (subdomain only)
|--------------------------------------------------------------------------
*/
Route::middleware('tenant')->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->middleware(['auth', 'verified'])->name('dashboard');

    require __DIR__.'/settings.php';
});
