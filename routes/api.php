<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

// Autenticação (pública)
Route::prefix('auth')->group(function () {
    Route::post('/google', 'Api\AuthController@loginGoogle');
    Route::post('/logout', 'Api\AuthController@logout')->middleware('auth:sanctum');
    Route::get('/user', 'Api\AuthController@user')->middleware('auth:sanctum');
    Route::post('/impersonate/{user}', 'Api\AuthController@impersonate')->middleware(['auth:sanctum', 'root']);
});

// Rotas protegidas
Route::middleware('auth:sanctum')->group(function () {
    
    // Personalizar
    Route::prefix('personalizar')->group(function () {
        Route::apiResource('collaborators', 'Api\CollaboratorController');
        Route::apiResource('entry-types', 'Api\EntryTypeController');
        Route::apiResource('exit-types', 'Api\ExitTypeController');
        Route::apiResource('service-types', 'Api\ServiceTypeController');
    });

    // Registros
    Route::prefix('registros')->group(function () {
        Route::apiResource('financial-records', 'Api\FinancialRecordController');
        Route::apiResource('work-shifts', 'Api\WorkShiftController');
        Route::apiResource('daily-closures', 'Api\DailyClosureController');
        Route::apiResource('commissions', 'Api\CommissionController');
        
        // Ações customizadas
        Route::post('daily-closures/{date}/close', 'Api\DailyClosureController@close');
        Route::get('balance', 'Api\BalanceController@current');
    });

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/summary', 'Api\DashboardController@summary');
        Route::get('/commissions-by-collaborator', 'Api\DashboardController@commissionsByCollaborator');
        Route::get('/top-profitable-days', 'Api\DashboardController@topProfitableDays');
        Route::get('/entries-exits', 'Api\DashboardController@entriesExits');
    });

    // Importação
    Route::post('/import', 'Api\ImportController@import')->middleware('root');
    Route::get('/import-logs', 'Api\ImportController@logs')->middleware('root');
});
