<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Catalog\Usecase;

use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportChecklistUsecaseInterface;
use src\Modules\Report\Domain\Catalog\ReportCatalog;

class ListReportChecklistUsecase implements ListReportChecklistUsecaseInterface
{
    /** @return list<array{key: string, label: string}> */
    public function __invoke(): array
    {
        $options = [];

        foreach (ReportCatalog::CHECKLIST as $key => $label) {
            $options[] = ['key' => $key, 'label' => $label];
        }

        return $options;
    }
}
