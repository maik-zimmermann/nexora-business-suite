<?php

use App\Http\Controllers\OnboardingController;
use Illuminate\Support\Facades\Route;

Route::middleware('onboarding.eligible')->group(function () {
    Route::get('onboarding/{user}', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('onboarding/{user}', [OnboardingController::class, 'store'])->name('onboarding.store');
});
