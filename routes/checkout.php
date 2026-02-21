<?php

use App\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('checkout/session', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
Route::get('checkout/cancelled', [CheckoutController::class, 'cancelled'])->name('checkout.cancelled');
