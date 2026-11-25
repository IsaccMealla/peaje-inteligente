<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('login');

Route::get('/auth/google/redirect', [AuthController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [AuthController::class, 'callback'])->name('google.callback');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [AuthController::class, 'dashboard'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});