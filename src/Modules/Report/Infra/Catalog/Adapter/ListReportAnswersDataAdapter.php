<?php

declare(strict_types=1);

namespace src\Modules\Report\Infra\Catalog\Adapter;

use src\Modules\Report\Application\Catalog\DTO\ListReportAnswersInputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportAnswersOutputDTO;
use src\Modules\Report\Application\Catalog\Interfaces\Adapter\ListReportAnswersDataAdapterInterface;

class ListReportAnswersDataAdapter implements ListReportAnswersDataAdapterInterface
{
    /** @param array{id?: string|null, code?: string|null, name?: string|null} $data */
    public function fromArray(array $data): ListReportAnswersInputDTO
    {
        return new ListReportAnswersInputDTO(
            id: $data['id'] ?? null,
            code: $data['code'] ?? null,
            name: $data['name'] ?? null,
        );
    }

    /** @return array{answers: list<array{key: string, label: string}>} */
    public function toArray(ListReportAnswersOutputDTO $data): array
    {
        return [
            'answers' => array_map(fn (array $item): array => [
                'key' => $item['code'],
                'label' => $item['name'],
            ], $data->items),
        ];
    }
}
