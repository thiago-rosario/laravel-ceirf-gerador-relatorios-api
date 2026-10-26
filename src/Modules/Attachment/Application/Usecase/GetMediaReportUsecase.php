<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Application\Usecase;

use src\Modules\Attachment\Application\DTO\GetMediaReportInputDTO;
use src\Modules\Attachment\Application\DTO\GetMediaReportOutputDTO;
use src\Modules\Attachment\Application\Interfaces\Service\ReportMediaRetrievalServiceInterface;
use src\Modules\Attachment\Application\Interfaces\Usecase\GetMediaReportUsecaseInterface;

class GetMediaReportUsecase implements GetMediaReportUsecaseInterface
{
    public function __construct(
        private readonly ReportMediaRetrievalServiceInterface $service
    ) {}

    public function __invoke(GetMediaReportInputDTO $input): GetMediaReportOutputDTO
    {
        return $this->service->get($input);
    }
}
