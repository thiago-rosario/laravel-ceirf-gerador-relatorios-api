<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Application\Interfaces\Service;

use src\Modules\Attachment\Application\DTO\UploadReportMediaInputDTO;
use src\Modules\Attachment\Application\DTO\UploadReportMediaOutputDTO;

interface ReportMediaUploadServiceInterface
{
    public function upload(UploadReportMediaInputDTO $input): UploadReportMediaOutputDTO;
}
