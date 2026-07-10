<?php

use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\MobileTaskController;
use App\Http\Controllers\Api\MobileDashboardController;
use Illuminate\Support\Facades\Route;

// ===== Public Mobile Auth Endpoint =====
Route::post('/login', [MobileAuthController::class, 'login']);

// ===== Sanctum Protected Mobile App Endpoints =====
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [MobileAuthController::class, 'logout']);
    Route::get('/me', [MobileAuthController::class, 'me']);

    // Tasks API
    Route::get('/tasks', [MobileTaskController::class, 'index']);
    Route::post('/tasks/{id}/progress', [MobileTaskController::class, 'submitProgress']);
    Route::post('/tasks/{id}/complete', [MobileTaskController::class, 'complete']);

    // Dashboard Info & Alerts API
    Route::get('/notifications', [MobileDashboardController::class, 'notifications']);
    Route::post('/notifications/{id}/read', [MobileDashboardController::class, 'readNotification']);
    Route::get('/stock-alerts', [MobileDashboardController::class, 'stockAlerts']);
});
