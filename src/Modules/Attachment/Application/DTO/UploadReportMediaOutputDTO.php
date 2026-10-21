<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Application\DTO;

use src\Modules\Report\Application\DTO\ReportFileReferenceDataDTO;

readonly class UploadReportMediaOutputDTO
{
    public function __construct(
        public string $id,
        public string $rootReportId,
        public ReportFileReferenceDataDTO $file,
    ) {}
}
