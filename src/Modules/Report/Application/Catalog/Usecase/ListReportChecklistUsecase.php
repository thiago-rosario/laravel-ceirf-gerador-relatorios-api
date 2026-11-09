<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Catalog\Usecase;

use src\Modules\Report\Application\Catalog\DTO\ListReportChecklistInputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportChecklistOutputDTO;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportChecklistUsecaseInterface;
use src\Modules\Report\Domain\Catalog\ReportCatalog;

class ListReportChecklistUsecase implements ListReportChecklistUsecaseInterface
{
    public function __invoke(ListReportChecklistInputDTO $input): ListReportChecklistOutputDTO
    {
        $items = [];

        foreach (ReportCatalog::CHECKLIST as $code => $name) {
            $items[] = [
                'code' => $code,
                'name' => $name,
            ];
        }

        return new ListReportChecklistOutputDTO(
            items: $items,
        );
    }
}
