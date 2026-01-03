<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use App\Http\Controllers\Api\AuthController;
use App\Http\Middleware\CheckSanctumToken;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('/login', function () {
    return Inertia::render('auth/Login');
})->name('login');

// OAuth Routes (precisam de session)
Route::prefix('auth')->group(function () {
    Route::get('/google', [AuthController::class, 'redirectToGoogle'])->name('google.redirect');
    Route::get('/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('google.callback');
});

Route::get('/auth/google/callback', function () {
    return Inertia::render('auth/GoogleCallback');
})->name('google.callback.page');

// Setup de Empresa (protegido)
Route::get('/setup-empresa', function () {
    return Inertia::render('SetupCompany');
})->middleware(['auth:sanctum'])->name('setup.company');

// Painel Principal (protegido)
Route::get('/painel', function () {
    return Inertia::render('Painel');
})->middleware(['auth:sanctum'])->name('painel');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth:sanctum', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
