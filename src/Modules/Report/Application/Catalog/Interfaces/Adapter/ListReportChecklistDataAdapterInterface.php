<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Catalog\Interfaces\Adapter;

use src\Modules\Report\Application\Catalog\DTO\ListReportChecklistInputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportChecklistOutputDTO;

interface ListReportChecklistDataAdapterInterface
{
    /** @param array{id?: string|null, code?: string|null, name?: string|null} $data */
    public function fromArray(array $data): ListReportChecklistInputDTO;

    /** @return array{checklist: list<array{key: string, label: string}>} */
    public function toArray(ListReportChecklistOutputDTO $data): array;
}
