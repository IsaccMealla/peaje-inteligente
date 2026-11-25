<?php

use App\Http\Controllers\Api\PeajeApiController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/google', [PeajeApiController::class, 'loginGoogle']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/procesar', [PeajeApiController::class, 'procesar']);
});