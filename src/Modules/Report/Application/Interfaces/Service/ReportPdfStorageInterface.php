<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Interfaces\Service;

use src\Modules\Report\Application\DTO\GeneratedReportDataDTO;
use src\Modules\Report\Application\DTO\ReportPdfFileDTO;

interface ReportPdfStorageInterface
{
    public function open(string $reportId, GeneratedReportDataDTO $document): ReportPdfFileDTO;
}
