<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Catalog\Interfaces\Adapter;

use src\Modules\Report\Application\Catalog\DTO\ListReportTypologiesInputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportTypologiesOutputDTO;

interface ListReportTypologiesDataAdapterInterface
{
    /** @param array{id?: string|null, code?: string|null, name?: string|null} $data */
    public function fromArray(array $data): ListReportTypologiesInputDTO;

    /** @return array{typologies: list<array{key: string, label: string}>} */
    public function toArray(ListReportTypologiesOutputDTO $data): array;
}
