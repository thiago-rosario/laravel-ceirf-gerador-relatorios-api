<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Catalog\Interfaces\Adapter;

use src\Modules\Report\Application\Catalog\DTO\ListReportInfrastructureInputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportInfrastructureOutputDTO;

interface ListReportInfrastructureDataAdapterInterface
{
    /** @param array{id?: string|null, code?: string|null, name?: string|null} $data */
    public function fromArray(array $data): ListReportInfrastructureInputDTO;

    /** @return array{infrastructure: list<array{key: string, label: string}>} */
    public function toArray(ListReportInfrastructureOutputDTO $data): array;
}
