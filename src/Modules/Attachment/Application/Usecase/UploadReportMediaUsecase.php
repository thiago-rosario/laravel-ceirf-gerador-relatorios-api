<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Application\Usecase;

use src\Modules\Attachment\Application\DTO\UploadReportMediaInputDTO;
use src\Modules\Attachment\Application\DTO\UploadReportMediaOutputDTO;
use src\Modules\Attachment\Application\Interfaces\Service\ReportMediaUploadServiceInterface;
use src\Modules\Attachment\Application\Interfaces\Usecase\UploadReportMediaUsecaseInterface;

class UploadReportMediaUsecase implements UploadReportMediaUsecaseInterface
{
    public function __construct(private readonly ReportMediaUploadServiceInterface $service) {}

    public function __invoke(UploadReportMediaInputDTO $input): UploadReportMediaOutputDTO
    {
        return $this->service->upload($input);
    }
}
