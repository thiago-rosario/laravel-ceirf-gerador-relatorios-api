<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Infra\Provider;

use Illuminate\Support\ServiceProvider;
use src\Modules\Attachment\Application\Interfaces\Adapter\UploadReportMediaDataAdapterInterface;
use src\Modules\Attachment\Application\Interfaces\Service\ReportMediaRegistryServiceInterface;
use src\Modules\Attachment\Application\Interfaces\Service\ReportMediaUploadServiceInterface;
use src\Modules\Attachment\Application\Interfaces\Usecase\UploadReportMediaUsecaseInterface;
use src\Modules\Attachment\Application\Usecase\UploadReportMediaUsecase;
use src\Modules\Attachment\Infra\Adapter\UploadReportMediaDataAdapter;
use src\Modules\Attachment\Infra\Service\ReportMediaRegistryService;
use src\Modules\Attachment\Infra\Service\ReportMediaUploadService;

class AttachmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UploadReportMediaDataAdapterInterface::class, UploadReportMediaDataAdapter::class);
        $this->app->bind(ReportMediaRegistryServiceInterface::class, ReportMediaRegistryService::class);
        $this->app->bind(ReportMediaUploadServiceInterface::class, ReportMediaUploadService::class);
        $this->app->bind(UploadReportMediaUsecaseInterface::class, UploadReportMediaUsecase::class);
    }
}
