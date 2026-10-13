<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Interfaces\Usecase;

use src\Modules\Report\Application\DTO\MunicipalityDataDTO;

interface ListReportMunicipalitiesUsecaseInterface
{
    /** @return list<MunicipalityDataDTO> */
    public function __invoke(): array;
}
