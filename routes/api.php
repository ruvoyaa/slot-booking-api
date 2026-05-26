<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\HoldController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth.jwt');
});

Route::get('/slots/availability', [AvailabilityController::class, 'index']);

Route::middleware('auth.jwt')->group(function (): void {
    Route::post('/slots/{slotId}/hold', [HoldController::class, 'store']);
    Route::post('/holds/{holdId}/confirm', [HoldController::class, 'confirm']);
    Route::delete('/holds/{holdId}', [HoldController::class, 'destroy']);
});
