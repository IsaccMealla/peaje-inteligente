<?php

use App\Http\Controllers\AgenteController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackofficeController;
use App\Http\Controllers\DashboardLiveController;
use App\Http\Controllers\InferenceController;
use App\Http\Controllers\RegistroController;
use App\Http\Controllers\UsuarioOAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('login');

Route::get('/auth/google/redirect', [AuthController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [AuthController::class, 'callback'])->name('google.callback');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [AuthController::class, 'dashboard'])->name('dashboard');
    Route::get('/dashboard/feed', [DashboardLiveController::class, 'feed'])->name('dashboard.feed');
    Route::get('/registros/{registro}/captura', [RegistroController::class, 'captura'])
        ->name('registros.captura');
    Route::get('/vehiculos', [BackofficeController::class, 'vehiculos'])->name('vehiculos.index');
    Route::post('/vehiculos', [BackofficeController::class, 'guardarVehiculo'])->name('vehiculos.store');
    Route::put('/vehiculos/{vehiculo}', [BackofficeController::class, 'actualizarVehiculo'])->name('vehiculos.update');
    Route::delete('/vehiculos/{vehiculo}', [BackofficeController::class, 'eliminarVehiculo'])->name('vehiculos.destroy');
    Route::get('/pasos', [BackofficeController::class, 'pasos'])->name('pasos.index');
    Route::get('/alertas', [BackofficeController::class, 'alertas'])->name('alertas.index');
    Route::patch('/alertas/{alerta}/leida', [BackofficeController::class, 'marcarAlertaLeida'])->name('alertas.leida');
    Route::get('/modelos', [BackofficeController::class, 'modelos'])->name('modelos.index');
    Route::get('/tarifas-casetas', [BackofficeController::class, 'tarifasCasetas'])->name('tarifas-casetas.index');
    Route::get('/agente', [AgenteController::class, 'pagina'])->name('agente.index');
    Route::post('/agente/chat', [AgenteController::class, 'chatWeb'])->name('agente.chat');
    Route::post('/agente/nueva-sesion', [AgenteController::class, 'nuevaSesion'])->name('agente.nueva-sesion');
    Route::get('/estado-inferencia', [InferenceController::class, 'index'])->name('estado-inferencia.index');
    Route::get('/estado-inferencia/comprobar', [InferenceController::class, 'comprobar'])->name('estado-inferencia.comprobar');
    Route::post('/estado-inferencia/analizar', [InferenceController::class, 'analizar'])->name('estado-inferencia.analizar');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/agente/consultar', [AgenteController::class, 'consultar'])
        ->name('agente.consultar');

    Route::middleware('admin')->group(function () {
        Route::post('/tarifas-casetas/tarifas', [BackofficeController::class, 'guardarTarifa'])->name('tarifas.store');
        Route::put('/tarifas-casetas/tarifas/{tarifa}', [BackofficeController::class, 'actualizarTarifa'])->name('tarifas.update');
        Route::delete('/tarifas-casetas/tarifas/{tarifa}', [BackofficeController::class, 'eliminarTarifa'])->name('tarifas.destroy');
        Route::post('/tarifas-casetas/casetas', [BackofficeController::class, 'guardarCaseta'])->name('casetas.store');
        Route::put('/tarifas-casetas/casetas/{caseta}', [BackofficeController::class, 'actualizarCaseta'])->name('casetas.update');
        Route::delete('/tarifas-casetas/casetas/{caseta}', [BackofficeController::class, 'eliminarCaseta'])->name('casetas.destroy');
        Route::get('/usuarios-oauth', [UsuarioOAuthController::class, 'index'])->name('usuarios-oauth.index');
        Route::post('/usuarios-oauth', [UsuarioOAuthController::class, 'store'])->name('usuarios-oauth.store');
        Route::put('/usuarios-oauth/{usuario}', [UsuarioOAuthController::class, 'update'])->name('usuarios-oauth.update');
        Route::patch('/usuarios-oauth/{usuario}/toggle', [UsuarioOAuthController::class, 'toggle'])->name('usuarios-oauth.toggle');
        Route::delete('/usuarios-oauth/{usuario}', [UsuarioOAuthController::class, 'destroy'])->name('usuarios-oauth.destroy');
    });
});
