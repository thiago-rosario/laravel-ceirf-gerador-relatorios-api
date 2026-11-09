<?php

declare(strict_types=1);

namespace src\Modules\Report\Infra\Catalog\Adapter;

use src\Modules\Report\Application\Catalog\DTO\ListReportChecklistInputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportChecklistOutputDTO;
use src\Modules\Report\Application\Catalog\Interfaces\Adapter\ListReportChecklistDataAdapterInterface;

class ListReportChecklistDataAdapter implements ListReportChecklistDataAdapterInterface
{
    /** @param array{id?: string|null, code?: string|null, name?: string|null} $data */
    public function fromArray(array $data): ListReportChecklistInputDTO
    {
        return new ListReportChecklistInputDTO(
            id: $data['id'] ?? null,
            code: $data['code'] ?? null,
            name: $data['name'] ?? null,
        );
    }

    /** @return array{checklist: list<array{key: string, label: string}>} */
    public function toArray(ListReportChecklistOutputDTO $data): array
    {
        return [
            'checklist' => array_map(fn (array $item): array => [
                'key' => $item['code'],
                'label' => $item['name'],
            ], $data->items),
        ];
    }
}
