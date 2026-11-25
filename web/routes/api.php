<?php

use App\Http\Controllers\AgenteController;
use App\Http\Controllers\Api\PeajeApiController;
use App\Http\Controllers\DashboardLiveController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/google', [PeajeApiController::class, 'loginGoogle']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/procesar', [PeajeApiController::class, 'procesar']);
    Route::post('/agente/consultar', [AgenteController::class, 'consultar']);
});

Route::prefix('v1')->group(function () {
    Route::post('/auth/google', [PeajeApiController::class, 'loginGoogle']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/dashboard/live', [DashboardLiveController::class, 'feed']);
        Route::post('/pasos/frame', [PeajeApiController::class, 'procesar']);
        Route::post('/placas/consultar', [AgenteController::class, 'consultar']);
        Route::post('/placas/consultar-imagen', [AgenteController::class, 'consultar']);
        Route::get('/placas/{placa}/historial', [AgenteController::class, 'historial']);
        Route::get('/casetas/{caseta}/resumen', [DashboardLiveController::class, 'casetaResumen']);
        Route::post('/agente/chat', [AgenteController::class, 'consultar']);
    });
});
