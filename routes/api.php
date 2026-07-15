<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampiController;
use App\Http\Controllers\Api\GrupoController;
use App\Http\Controllers\Api\LocalController;
use App\Http\Controllers\Api\LocalIndisponibilidadeController;
use App\Http\Controllers\Api\PeriodoController;
use App\Http\Controllers\Api\ReservaController;
use App\Http\Controllers\Api\UserController;
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

// Rotas de coleção específicas (precisam vir antes das rotas com {parâmetro})
Route::middleware('auth:sanctum')->group(function () {
    Route::get('reservas/pendentes', [ReservaController::class, 'pendentes']);
});

// Leitura pública (Home, CampiDetail)
Route::get('campi', [CampiController::class, 'index']);
Route::get('campi/{campi}', [CampiController::class, 'show']);
Route::get('grupos', [GrupoController::class, 'index']);
Route::get('grupos/{grupo}', [GrupoController::class, 'show']);
Route::get('locais', [LocalController::class, 'index']);
Route::get('locais/{local}', [LocalController::class, 'show']);
Route::get('locais/{local}/gerentes', [LocalController::class, 'gerentes']);
Route::get('locais/{local}/indisponibilidades', [LocalIndisponibilidadeController::class, 'index']);
Route::get('reservas', [ReservaController::class, 'index']);
Route::get('reservas/{reserva}', [ReservaController::class, 'show']);
Route::get('periodos', [PeriodoController::class, 'index']);
Route::get('periodos/{periodo}', [PeriodoController::class, 'show']);

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

    Route::post('locais', [LocalController::class, 'store']);
    Route::put('locais/{local}', [LocalController::class, 'update']);
    Route::patch('locais/{local}', [LocalController::class, 'update']);
    Route::put('locais/{local}/gerentes', [LocalController::class, 'setGerentes']);
    Route::delete('locais/{local}', [LocalController::class, 'destroy']);

    Route::get('users', [UserController::class, 'index']);
    Route::get('minhas-reservas', [ReservaController::class, 'minhas']);
    Route::post('reservas', [ReservaController::class, 'store']);
    Route::post('reservas/bulk', [ReservaController::class, 'bulk']);
    Route::patch('reservas/{reserva}/aprovar', [ReservaController::class, 'aprovar']);
    Route::patch('reservas/{reserva}/cancelar', [ReservaController::class, 'cancelar']);
    Route::put('reservas/{reserva}', [ReservaController::class, 'update']);
    Route::patch('reservas/{reserva}', [ReservaController::class, 'update']);
    Route::delete('reservas/{reserva}', [ReservaController::class, 'destroy']);

    Route::post('periodos', [PeriodoController::class, 'store']);
    Route::put('periodos/{periodo}', [PeriodoController::class, 'update']);
    Route::patch('periodos/{periodo}', [PeriodoController::class, 'update']);
    Route::delete('periodos/{periodo}', [PeriodoController::class, 'destroy']);

    Route::post('locais/{local}/indisponibilidades', [LocalIndisponibilidadeController::class, 'store']);
    Route::put('indisponibilidades/{indisponibilidade}', [LocalIndisponibilidadeController::class, 'update']);
    Route::patch('indisponibilidades/{indisponibilidade}', [LocalIndisponibilidadeController::class, 'update']);
    Route::delete('indisponibilidades/{indisponibilidade}', [LocalIndisponibilidadeController::class, 'destroy']);
});
