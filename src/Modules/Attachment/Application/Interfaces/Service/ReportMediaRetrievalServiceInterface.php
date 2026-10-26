<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Application\Interfaces\Service;

use src\Modules\Attachment\Application\DTO\GetMediaReportInputDTO;
use src\Modules\Attachment\Application\DTO\GetMediaReportOutputDTO;

interface ReportMediaRetrievalServiceInterface
{
    public function get(GetMediaReportInputDTO $input): GetMediaReportOutputDTO;
}
