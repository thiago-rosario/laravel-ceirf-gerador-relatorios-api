<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Catalog\Interfaces\Usecase;

use src\Modules\Report\Application\Catalog\DTO\ListReportAnswersInputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportAnswersOutputDTO;

interface ListReportAnswersUsecaseInterface
{
    public function __invoke(ListReportAnswersInputDTO $input): ListReportAnswersOutputDTO;
}
