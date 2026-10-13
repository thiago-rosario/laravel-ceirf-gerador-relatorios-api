<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Usecase;

use src\Modules\Report\Application\Interfaces\Repository\ReportConsultationRepositoryInterface;
use src\Modules\Report\Application\Interfaces\Usecase\ListReportMunicipalitiesUsecaseInterface;

class ListReportMunicipalitiesUsecase implements ListReportMunicipalitiesUsecaseInterface
{
    public function __construct(private readonly ReportConsultationRepositoryInterface $repository) {}

    public function __invoke(): array
    {
        return $this->repository->municipalities();
    }
}
