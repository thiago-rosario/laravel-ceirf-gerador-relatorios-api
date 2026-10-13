<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Interfaces\Usecase;

use src\Modules\Report\Application\DTO\FindByIdReportInputDTO;
use src\Modules\Report\Application\DTO\ReportPdfFileDTO;

interface GetReportPdfUsecaseInterface
{
    public function __invoke(FindByIdReportInputDTO $input): ReportPdfFileDTO;
}
