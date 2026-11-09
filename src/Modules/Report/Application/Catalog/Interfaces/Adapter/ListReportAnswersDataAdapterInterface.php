<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Catalog\Interfaces\Adapter;

use src\Modules\Report\Application\Catalog\DTO\ListReportAnswersInputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportAnswersOutputDTO;

interface ListReportAnswersDataAdapterInterface
{
    /** @param array{id?: string|null, code?: string|null, name?: string|null} $data */
    public function fromArray(array $data): ListReportAnswersInputDTO;

    /** @return array{answers: list<array{key: string, label: string}>} */
    public function toArray(ListReportAnswersOutputDTO $data): array;
}
