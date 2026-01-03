<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CollaboratorController;
use App\Http\Controllers\Api\EntryTypeController;
use App\Http\Controllers\Api\ExitTypeController;
use App\Http\Controllers\Api\ServiceTypeController;
use App\Http\Controllers\Api\FinancialRecordController;
use App\Http\Controllers\Api\WorkShiftController;
use App\Http\Controllers\Api\DailyClosureController;
use App\Http\Controllers\Api\CommissionController;
use App\Http\Controllers\Api\BalanceController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ImportController;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

// Autenticação (pública)
Route::prefix('auth')->group(function () {
    Route::post('/google', [AuthController::class, 'loginGoogle']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/user', [AuthController::class, 'user'])->middleware('auth:sanctum');
    Route::post('/impersonate/{user}', [AuthController::class, 'impersonate'])->middleware(['auth:sanctum', 'root']);
});

// Rotas protegidas
Route::middleware('auth:sanctum')->group(function () {
    
    // Personalizar
    Route::prefix('personalizar')->group(function () {
        Route::apiResource('collaborators', CollaboratorController::class);
        Route::apiResource('entry-types', EntryTypeController::class);
        Route::apiResource('exit-types', ExitTypeController::class);
        Route::apiResource('service-types', ServiceTypeController::class);
    });

    // Registros
    Route::prefix('registros')->group(function () {
        Route::apiResource('financial-records', FinancialRecordController::class);
        Route::apiResource('work-shifts', WorkShiftController::class);
        Route::apiResource('daily-closures', DailyClosureController::class);
        Route::apiResource('commissions', CommissionController::class);
        
        // Ações customizadas
        Route::post('daily-closures/{date}/close', [DailyClosureController::class, 'close']);
        Route::get('balance', [BalanceController::class, 'current']);
    });

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/summary', [DashboardController::class, 'summary']);
        Route::get('/commissions-by-collaborator', [DashboardController::class, 'commissionsByCollaborator']);
        Route::get('/top-profitable-days', [DashboardController::class, 'topProfitableDays']);
        Route::get('/entries-exits', [DashboardController::class, 'entriesExits']);
    });

    // Importação
    Route::post('/import', [ImportController::class, 'import'])->middleware('root');
    Route::get('/import-logs', [ImportController::class, 'logs'])->middleware('root');
});
