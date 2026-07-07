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
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\HistoryController;
use App\Models\Collaborator;
use App\Models\FinancialRecord;

// Route model binding scoped por empresa
Route::bind('collaborator', function ($value) {
    return Collaborator::forCompany()->findOrFail($value);
});

Route::bind('financialRecord', function ($value) {
    return FinancialRecord::forCompany()->findOrFail($value);
});

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

// Autenticação (pública)
Route::prefix('auth')->group(function () {
    Route::post('/google', [AuthController::class, 'loginGoogle']);
    Route::get('/pending-registration', [AuthController::class, 'pendingRegistration']);
    Route::post('/register', [AuthController::class, 'registerGoogle']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/user', [AuthController::class, 'user'])->middleware('auth:sanctum');
    Route::post('/impersonate/{user}', [AuthController::class, 'impersonate'])->middleware(['auth:sanctum', 'root']);
});

// ========================================================================
// ROTAS DE LEITURA (GET) - SEM RASTREAMENTO
// ========================================================================
Route::middleware(['auth:sanctum', 'company.isolation'])->group(function () {
    
    // Histórico (Auditoria) - leitura apenas
    Route::prefix('history')->group(function () {
        Route::get('/stats', [HistoryController::class, 'stats']);
        Route::get('/model-history', [HistoryController::class, 'modelHistory']);
        Route::get('/{id}', [HistoryController::class, 'show']);
        Route::get('/', [HistoryController::class, 'index']);
    });

    // Dashboard (leitura)
    Route::prefix('dashboard')->group(function () {
        Route::get('/summary', [DashboardController::class, 'summary']);
        Route::get('/commissions-by-collaborator', [DashboardController::class, 'commissionsByCollaborator']);
        Route::get('/top-profitable-days', [DashboardController::class, 'topProfitableDays']);
        Route::get('/entries-exits', [DashboardController::class, 'entriesExits']);
    });

    // Empresa (leitura)
    Route::prefix('company')->group(function () {
        Route::get('/', [CompanyController::class, 'show'])->name('company.show');
        Route::get('/needs-setup', [CompanyController::class, 'needsSetup'])->name('company.needs-setup');
    });

    // Personalizar - Leitura (GET apenas)
    Route::prefix('personalizar')->group(function () {
        Route::get('collaborators', [CollaboratorController::class, 'index']);
        Route::get('collaborators/with-trashed', [CollaboratorController::class, 'withTrashed']);
        Route::get('collaborators/{collaborator}', [CollaboratorController::class, 'show']);

        Route::get('entry-types', [EntryTypeController::class, 'index']);
        Route::get('entry-types/with-trashed', [EntryTypeController::class, 'withTrashed']);
        Route::get('entry-types/{entryType}', [EntryTypeController::class, 'show']);

        Route::get('exit-types', [ExitTypeController::class, 'index']);
        Route::get('exit-types/with-trashed', [ExitTypeController::class, 'withTrashed']);
        Route::get('exit-types/{exitType}', [ExitTypeController::class, 'show']);

        Route::get('service-types', [ServiceTypeController::class, 'index']);
        Route::get('service-types/with-trashed', [ServiceTypeController::class, 'withTrashed']);
        Route::get('service-types/{serviceType}', [ServiceTypeController::class, 'show']);
    });

    // Registros - Leitura (GET apenas)
    Route::prefix('registros')->group(function () {
        Route::get('financial-records', [FinancialRecordController::class, 'index']);
        Route::get('financial-records/{financialRecord}', [FinancialRecordController::class, 'show']);
        Route::get('financial-records/by-date/{date}', [FinancialRecordController::class, 'byDate']);
        
        Route::get('work-shifts', [WorkShiftController::class, 'index']);
        Route::get('work-shifts/{id}', [WorkShiftController::class, 'show']);
        Route::get('work-shifts/by-date/{date}', [WorkShiftController::class, 'byDate']);
        Route::get('work-shifts/collaborator/{collaboratorId}', [WorkShiftController::class, 'collaboratorShifts']);
        
        Route::get('daily-closures', [DailyClosureController::class, 'index']);
        Route::get('daily-closures/{id}', [DailyClosureController::class, 'show']);
        Route::get('daily-closures/is-closed/{date}', [DailyClosureController::class, 'isClosed']);
        
        Route::get('commissions', [CommissionController::class, 'index']);
        Route::get('commissions/{id}', [CommissionController::class, 'show']);
        Route::get('commissions/collaborator/{collaboratorId}/unpaid', [CommissionController::class, 'unpaidCommissions']);
        Route::get('commissions/collaborator/{collaboratorId}', [CommissionController::class, 'collaboratorCommissions']);
        
        Route::get('balance', [BalanceController::class, 'history']);
    });

    // Import logs (leitura)
    Route::get('/import-logs', [ImportController::class, 'logs'])->middleware('root');
});

// ========================================================================
// ROTAS DE ESCRITA (POST/PUT/DELETE) - COM RASTREAMENTO
// ========================================================================
Route::middleware(['auth:sanctum', 'company.isolation', 'track.changes'])->group(function () {
    
    // Empresa - Escrita
    Route::prefix('company')->group(function () {
        Route::put('/', [CompanyController::class, 'update'])->name('company.update');
        Route::delete('/', [CompanyController::class, 'destroy'])->name('company.destroy');
    });
    
    // Personalizar - Escrita
    Route::prefix('personalizar')->group(function () {
        // Colaboradores
        Route::post('collaborators/get-or-create', [CollaboratorController::class, 'getOrCreate']);
        Route::post('collaborators/{id}/restore', [CollaboratorController::class, 'restore']);
        Route::post('collaborators', [CollaboratorController::class, 'store']);
        Route::put('collaborators/{collaborator}', [CollaboratorController::class, 'update']);
        Route::delete('collaborators/{collaborator}', [CollaboratorController::class, 'destroy']);
        
        // Tipos de Entrada
        Route::post('entry-types/{id}/restore', [EntryTypeController::class, 'restore']);
        Route::post('entry-types', [EntryTypeController::class, 'store']);
        Route::put('entry-types/{entryType}', [EntryTypeController::class, 'update']);
        Route::delete('entry-types/{entryType}', [EntryTypeController::class, 'destroy']);
        
        // Tipos de Saída
        Route::post('exit-types/{id}/restore', [ExitTypeController::class, 'restore']);
        Route::post('exit-types', [ExitTypeController::class, 'store']);
        Route::put('exit-types/{exitType}', [ExitTypeController::class, 'update']);
        Route::delete('exit-types/{exitType}', [ExitTypeController::class, 'destroy']);
        
        // Tipos de Serviço
        Route::post('service-types/{id}/restore', [ServiceTypeController::class, 'restore']);
        Route::post('service-types', [ServiceTypeController::class, 'store']);
        Route::put('service-types/{serviceType}', [ServiceTypeController::class, 'update']);
        Route::delete('service-types/{serviceType}', [ServiceTypeController::class, 'destroy']);
    });

    // Registros - Escrita
    Route::prefix('registros')->group(function () {
        // Financial Records
        Route::delete('financial-records/clear-all', [FinancialRecordController::class, 'clearAll']);
        Route::post('financial-records/between-dates', [FinancialRecordController::class, 'betweenDates']);
        Route::post('financial-records/daily-totals', [FinancialRecordController::class, 'dailyTotals']);
        Route::post('financial-records/{id}/restore', [FinancialRecordController::class, 'restore']);
        Route::post('financial-records', [FinancialRecordController::class, 'store']);
        Route::put('financial-records/{financialRecord}', [FinancialRecordController::class, 'update']);
        Route::delete('financial-records/{financialRecord}', [FinancialRecordController::class, 'destroy']);
        
        // Work Shifts
        Route::post('work-shifts', [WorkShiftController::class, 'store']);
        Route::put('work-shifts/{id}', [WorkShiftController::class, 'update']);
        Route::delete('work-shifts/{id}', [WorkShiftController::class, 'destroy']);
        Route::post('work-shifts/monthly-schedule', [WorkShiftController::class, 'monthlySchedule']);
        
        // Daily Closures
        Route::post('daily-closures', [DailyClosureController::class, 'store']);
        Route::put('daily-closures/{id}', [DailyClosureController::class, 'update']);
        Route::delete('daily-closures/{id}', [DailyClosureController::class, 'destroy']);
        Route::post('daily-closures/reopen/{dailyClosure}', [DailyClosureController::class, 'reopen']);
        Route::post('daily-closures/summary', [DailyClosureController::class, 'summary']);
        
        // Commissions
        Route::post('commissions/generate-from-date', [CommissionController::class, 'generateFromDate']);
        Route::post('commissions/mark-as-paid', [CommissionController::class, 'markAsPaid']);
        Route::post('commissions/summary-by-collaborator', [CommissionController::class, 'summaryByCollaborator']);
        Route::post('commissions/summary-by-month', [CommissionController::class, 'summaryByMonth']);
        Route::post('commissions', [CommissionController::class, 'store']);
        Route::put('commissions/{id}', [CommissionController::class, 'update']);
        Route::delete('commissions/{id}', [CommissionController::class, 'destroy']);
        
        // Balance
        Route::post('balance', [BalanceController::class, 'store']);
        Route::post('balance/daily-progression', [BalanceController::class, 'dailyProgression']);
        Route::post('balance/monthly-summary', [BalanceController::class, 'monthlySummary']);
        Route::post('balance/yearly-summary', [BalanceController::class, 'yearlySummary']);
    });

    // Importação
    Route::post('/import', [ImportController::class, 'import'])->middleware('root');

    // Suporte
    Route::post('/support', [SupportController::class, 'send']);
});
