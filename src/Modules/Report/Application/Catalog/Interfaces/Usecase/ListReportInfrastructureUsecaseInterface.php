<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Catalog\Interfaces\Usecase;

use src\Modules\Report\Application\Catalog\DTO\ListReportInfrastructureInputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportInfrastructureOutputDTO;

interface ListReportInfrastructureUsecaseInterface
{
    public function __invoke(ListReportInfrastructureInputDTO $input): ListReportInfrastructureOutputDTO;
}
