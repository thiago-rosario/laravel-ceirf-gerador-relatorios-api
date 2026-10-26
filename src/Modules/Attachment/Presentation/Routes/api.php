<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use src\Modules\Attachment\Presentation\Http\Controllers\GetMediaReportController;
use src\Modules\Attachment\Presentation\Http\Controllers\UploadReportMediaController;
use src\Modules\Identity\Presentation\Http\Middleware\EnsurePasswordIsChanged;

Route::middleware(['auth:api', EnsurePasswordIsChanged::class])->prefix('reports')->name('reports.')->group(function (): void {
    Route::post('/{id}/media', UploadReportMediaController::class)->name('media.store');
    Route::get('/{id}/media/{mediaId}', GetMediaReportController::class)->name('media.show');
});
