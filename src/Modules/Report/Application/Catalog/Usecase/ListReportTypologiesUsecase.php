<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Catalog\Usecase;

use src\Modules\Report\Application\Catalog\Interfaces\Repository\ReportCatalogRepositoryInterface;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportTypologiesUsecaseInterface;

class ListReportTypologiesUsecase implements ListReportTypologiesUsecaseInterface
{
    public function __construct(
        private readonly ReportCatalogRepositoryInterface $repository
    ){}

    /** @return list<array{key: string, label: string}> */
    public function __invoke(): array
    {
        return $this->repository->typologies();
    }
}
