<?php

declare(strict_types=1);

namespace src\Modules\Report\Infra\Catalog\Adapter;

use src\Modules\Report\Application\Catalog\DTO\ListReportInfrastructureInputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportInfrastructureOutputDTO;
use src\Modules\Report\Application\Catalog\Interfaces\Adapter\ListReportInfrastructureDataAdapterInterface;

class ListReportInfrastructureDataAdapter implements ListReportInfrastructureDataAdapterInterface
{
    /** @param array{id?: string|null, code?: string|null, name?: string|null} $data */
    public function fromArray(array $data): ListReportInfrastructureInputDTO
    {
        return new ListReportInfrastructureInputDTO(
            id: $data['id'] ?? null,
            code: $data['code'] ?? null,
            name: $data['name'] ?? null,
        );
    }

    /** @return array{infrastructure: list<array{key: string, label: string}>} */
    public function toArray(ListReportInfrastructureOutputDTO $data): array
    {
        return [
            'infrastructure' => array_map(fn (array $item): array => [
                'key' => $item['code'],
                'label' => $item['name'],
            ], $data->items),
        ];
    }
}
