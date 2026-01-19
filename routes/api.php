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
    
    // Empresa
    Route::prefix('company')->group(function () {
        Route::get('/', [CompanyController::class, 'show'])->name('company.show');
        Route::put('/', [CompanyController::class, 'update'])->name('company.update');
        Route::delete('/', [CompanyController::class, 'destroy'])->name('company.destroy');
        Route::get('/needs-setup', [CompanyController::class, 'needsSetup'])->name('company.needs-setup');
    });
    
    // Personalizar
    Route::prefix('personalizar')->group(function () {
        Route::get('collaborators/with-trashed', [CollaboratorController::class, 'withTrashed']);
        Route::post('collaborators/get-or-create', [CollaboratorController::class, 'getOrCreate']);
        Route::post('collaborators/{id}/restore', [CollaboratorController::class, 'restore']);
        Route::apiResource('collaborators', CollaboratorController::class);
        
        Route::get('entry-types/with-trashed', [EntryTypeController::class, 'withTrashed']);
        Route::post('entry-types/{id}/restore', [EntryTypeController::class, 'restore']);
        Route::apiResource('entry-types', EntryTypeController::class);
        
        Route::get('exit-types/with-trashed', [ExitTypeController::class, 'withTrashed']);
        Route::post('exit-types/{id}/restore', [ExitTypeController::class, 'restore']);
        Route::apiResource('exit-types', ExitTypeController::class);
        
        Route::get('service-types/with-trashed', [ServiceTypeController::class, 'withTrashed']);
        Route::post('service-types/{id}/restore', [ServiceTypeController::class, 'restore']);
        Route::apiResource('service-types', ServiceTypeController::class);
    });

    // Registros
    Route::prefix('registros')->group(function () {
        // Custom actions must come before the resource to avoid conflicts
        Route::post('financial-records/{id}/restore', [FinancialRecordController::class, 'restore']);
        Route::apiResource('financial-records', FinancialRecordController::class);
        Route::get('financial-records/by-date/{date}', [FinancialRecordController::class, 'byDate']);
        Route::post('financial-records/between-dates', [FinancialRecordController::class, 'betweenDates']);
        Route::post('financial-records/daily-totals', [FinancialRecordController::class, 'dailyTotals']);
        
        Route::apiResource('work-shifts', WorkShiftController::class);
        Route::get('work-shifts/by-date/{date}', [WorkShiftController::class, 'byDate']);
        Route::get('work-shifts/collaborator/{collaboratorId}', [WorkShiftController::class, 'collaboratorShifts']);
        Route::post('work-shifts/monthly-schedule', [WorkShiftController::class, 'monthlySchedule']);
        
        Route::apiResource('daily-closures', DailyClosureController::class);
        Route::post('daily-closures/reopen/{dailyClosure}', [DailyClosureController::class, 'reopen']);
        Route::post('daily-closures/summary', [DailyClosureController::class, 'summary']);
        Route::get('daily-closures/is-closed/{date}', [DailyClosureController::class, 'isClosed']);
        
        // Commission routes - custom actions FIRST
        Route::post('commissions/generate-from-date', [CommissionController::class, 'generateFromDate']);
        Route::post('commissions/mark-as-paid', [CommissionController::class, 'markAsPaid']);
        Route::post('commissions/summary-by-collaborator', [CommissionController::class, 'summaryByCollaborator']);
        Route::get('commissions/collaborator/{collaboratorId}/unpaid', [CommissionController::class, 'unpaidCommissions']);
        Route::get('commissions/collaborator/{collaboratorId}', [CommissionController::class, 'collaboratorCommissions']);
        // Commission CRUD last (these match /commissions/{id})
        Route::apiResource('commissions', CommissionController::class);
        
        Route::get('balance', [BalanceController::class, 'history']);
        Route::post('balance', [BalanceController::class, 'store']);
        Route::post('balance/daily-progression', [BalanceController::class, 'dailyProgression']);
        Route::post('balance/monthly-summary', [BalanceController::class, 'monthlySummary']);
        Route::post('balance/yearly-summary', [BalanceController::class, 'yearlySummary']);
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

    // Suporte
    Route::post('/support', [SupportController::class, 'send']);
});
