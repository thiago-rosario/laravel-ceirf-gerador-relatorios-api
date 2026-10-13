<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use src\Modules\Identity\Presentation\Http\Middleware\EnsurePasswordIsChanged;
use src\Modules\Report\Presentation\Http\Controllers\CountAllReportsController;
use src\Modules\Report\Presentation\Http\Controllers\CountReportsByMonthController;
use src\Modules\Report\Presentation\Http\Controllers\CreateReportController;
use src\Modules\Report\Presentation\Http\Controllers\CreateReportRevisionController;
use src\Modules\Report\Presentation\Http\Controllers\DownloadReportPdfController;
use src\Modules\Report\Presentation\Http\Controllers\FindByIdReportController;
use src\Modules\Report\Presentation\Http\Controllers\FindLatestReportController;
use src\Modules\Report\Presentation\Http\Controllers\FindReportHistoryController;
use src\Modules\Report\Presentation\Http\Controllers\GenerateReportController;
use src\Modules\Report\Presentation\Http\Controllers\GetDashboardReportsController;
use src\Modules\Report\Presentation\Http\Controllers\ListReportMunicipalitiesController;
use src\Modules\Report\Presentation\Http\Controllers\ListReportsController;
use src\Modules\Report\Presentation\Http\Controllers\UpdateReportController;

Route::middleware(['auth:api', EnsurePasswordIsChanged::class])->prefix('reports')->name('reports.')->group(function (): void {
    Route::post('/', CreateReportController::class)->name('create');
    Route::get('/', ListReportsController::class)->name('list');
    Route::get('/municipalities', ListReportMunicipalitiesController::class)->name('municipalities');
    Route::get('/dashboard', GetDashboardReportsController::class)->name('dashboard');
    Route::get('/count', CountAllReportsController::class)->name('count');
    Route::get('/count-by-month', CountReportsByMonthController::class)->name('count-by-month');
    Route::get('/{id}', FindByIdReportController::class)->name('find');
    Route::patch('/{id}', UpdateReportController::class)->name('update');
    Route::get('/{id}/latest', FindLatestReportController::class)->name('latest');
    Route::get('/{id}/history', FindReportHistoryController::class)->name('history');
    Route::post('/{id}/revisions', CreateReportRevisionController::class)->name('revisions.create');
    Route::post('/{id}/generate', GenerateReportController::class)->name('generate');
    Route::get('/{id}/pdf', DownloadReportPdfController::class)->name('pdf');
});
