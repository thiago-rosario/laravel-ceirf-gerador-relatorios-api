<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Catalog\Usecase;

use src\Modules\Report\Application\Catalog\DTO\ListReportInfrastructureInputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportInfrastructureOutputDTO;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportInfrastructureUsecaseInterface;
use src\Modules\Report\Domain\Catalog\ReportCatalog;

class ListReportInfrastructureUsecase implements ListReportInfrastructureUsecaseInterface
{
    public function __invoke(ListReportInfrastructureInputDTO $input): ListReportInfrastructureOutputDTO
    {
        $items = [];

        foreach (ReportCatalog::INFRASTRUCTURE as $code => $name) {
            $items[] = [
                'code' => $code,
                'name' => $name,
            ];
        }

        return new ListReportInfrastructureOutputDTO(
            items: $items,
        );
    }
}
