<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Catalog\Interfaces\Usecase;

use src\Modules\Report\Application\Catalog\DTO\ListReportChecklistInputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportChecklistOutputDTO;

interface ListReportChecklistUsecaseInterface
{
    public function __invoke(ListReportChecklistInputDTO $input): ListReportChecklistOutputDTO;
}
