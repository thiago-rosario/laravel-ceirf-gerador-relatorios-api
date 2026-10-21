<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Application\Interfaces\Usecase;

use src\Modules\Attachment\Application\DTO\UploadReportMediaInputDTO;
use src\Modules\Attachment\Application\DTO\UploadReportMediaOutputDTO;

interface UploadReportMediaUsecaseInterface
{
    public function __invoke(UploadReportMediaInputDTO $input): UploadReportMediaOutputDTO;
}
