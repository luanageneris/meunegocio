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

Route::get('/registre-se', function () {
    return Inertia::render('auth/Register');
})->name('register.form');

// OAuth Routes (precisam de session)
Route::prefix('auth')->group(function () {
    Route::get('/google', [AuthController::class, 'redirectToGoogle'])->name('google.redirect');
    Route::get('/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('google.callback');
});

// Página de processamento OAuth (recebe token e redireciona)
Route::get('/oauth/callback', function () {
    return Inertia::render('auth/GoogleCallback');
})->name('oauth.callback.page');

// Setup de Empresa
Route::get('/setup-empresa', function () {
    return Inertia::render('SetupCompany');
})->middleware(['auth:sanctum', 'verified'])->name('setup.company');

// Painel Principal (legado -> redireciona para Dashboard)
Route::redirect('/painel', '/dashboard')->name('painel');

// Rotas privadas - require authentication
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // Registros
    Route::get('/registros', function () {
        return Inertia::render('Registros');
    })->name('registros');

    // Personalizar
    Route::get('/personalizar', function () {
        return Inertia::render('Customize');
    })->name('personalizar');

    // Minha conta
    Route::get('/minha-conta', function () {
        return Inertia::render('MinhaConta');
    })->name('minha-conta');

    // Colaboradores
    Route::get('/colaboradores', function () {
        return Inertia::render('Colaboradores');
    })->name('colaboradores');

    // Histórico (Auditoria)
    Route::get('/historico', function () {
        return Inertia::render('Historico');
    })->name('historico');

    // Ajuda e Suporte
    Route::get('/suporte', function () {
        return Inertia::render('Suporte');
    })->name('suporte');
});

// Política de Privacidade (LGPD Brasil e Portugal)
Route::get('/politica-de-privacidade', function () {
    return Inertia::render('PoliticaPrivacidade');
})->name('politica-privacidade');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth:sanctum', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
