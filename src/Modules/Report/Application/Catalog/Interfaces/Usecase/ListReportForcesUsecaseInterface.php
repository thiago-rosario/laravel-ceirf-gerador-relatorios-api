<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Catalog\Interfaces\Usecase;

use src\Modules\Report\Application\Catalog\DTO\ListReportForcesInputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportForcesOutputDTO;

interface ListReportForcesUsecaseInterface
{
    public function __invoke(ListReportForcesInputDTO $input): ListReportForcesOutputDTO;
}
