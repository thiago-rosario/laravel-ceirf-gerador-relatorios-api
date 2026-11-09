<?php

declare(strict_types=1);

namespace src\Modules\Report\Infra\Catalog\Adapter;

use src\Modules\Report\Application\Catalog\DTO\ListReportTypologiesInputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportTypologiesOutputDTO;
use src\Modules\Report\Application\Catalog\Interfaces\Adapter\ListReportTypologiesDataAdapterInterface;

class ListReportTypologiesDataAdapter implements ListReportTypologiesDataAdapterInterface
{
    /** @param array{id?: string|null, code?: string|null, name?: string|null} $data */
    public function fromArray(array $data): ListReportTypologiesInputDTO
    {
        return new ListReportTypologiesInputDTO(
            id: $data['id'] ?? null,
            code: $data['code'] ?? null,
            name: $data['name'] ?? null,
        );
    }

    /** @return array{typologies: list<array{key: string, label: string}>} */
    public function toArray(ListReportTypologiesOutputDTO $data): array
    {
        return [
            'typologies' => array_map(fn (array $item): array => [
                'key' => $item['code'],
                'label' => $item['name'],
            ], $data->items),
        ];
    }
}
