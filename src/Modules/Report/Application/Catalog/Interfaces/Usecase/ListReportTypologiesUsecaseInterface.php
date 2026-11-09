<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Catalog\Interfaces\Usecase;

use src\Modules\Report\Application\Catalog\DTO\ListReportTypologiesInputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportTypologiesOutputDTO;

interface ListReportTypologiesUsecaseInterface
{
    public function __invoke(ListReportTypologiesInputDTO $input): ListReportTypologiesOutputDTO;
}
