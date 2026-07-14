<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampiController;
use App\Http\Controllers\Api\GrupoController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('resend-otp', [AuthController::class, 'resendOtp']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::post('provider/{provider}', [AuthController::class, 'loginWithProvider']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

// Leitura pública (Home, CampiDetail)
Route::get('campi', [CampiController::class, 'index']);
Route::get('campi/{campi}', [CampiController::class, 'show']);
Route::get('grupos', [GrupoController::class, 'index']);
Route::get('grupos/{grupo}', [GrupoController::class, 'show']);

// Escrita protegida (admin)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('campi', [CampiController::class, 'store']);
    Route::put('campi/{campi}', [CampiController::class, 'update']);
    Route::patch('campi/{campi}', [CampiController::class, 'update']);
    Route::delete('campi/{campi}', [CampiController::class, 'destroy']);

    Route::post('grupos', [GrupoController::class, 'store']);
    Route::put('grupos/{grupo}', [GrupoController::class, 'update']);
    Route::patch('grupos/{grupo}', [GrupoController::class, 'update']);
    Route::delete('grupos/{grupo}', [GrupoController::class, 'destroy']);
});
