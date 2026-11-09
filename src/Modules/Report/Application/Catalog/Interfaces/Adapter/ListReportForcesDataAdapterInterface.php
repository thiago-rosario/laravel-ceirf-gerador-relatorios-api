<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Catalog\Interfaces\Adapter;

use src\Modules\Report\Application\Catalog\DTO\ListReportForcesInputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportForcesOutputDTO;

interface ListReportForcesDataAdapterInterface
{
    /** @param array{id?: string|null, code?: string|null, name?: string|null} $data */
    public function fromArray(array $data): ListReportForcesInputDTO;

    /** @return array{forces: list<array{key: string, label: string, name: string}>} */
    public function toArray(ListReportForcesOutputDTO $data): array;
}
