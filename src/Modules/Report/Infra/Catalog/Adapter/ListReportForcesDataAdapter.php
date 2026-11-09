<?php

declare(strict_types=1);

namespace src\Modules\Report\Infra\Catalog\Adapter;

use src\Modules\Report\Application\Catalog\DTO\ListReportForcesInputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportForcesOutputDTO;
use src\Modules\Report\Application\Catalog\Interfaces\Adapter\ListReportForcesDataAdapterInterface;

class ListReportForcesDataAdapter implements ListReportForcesDataAdapterInterface
{
    /** @param array{id?: string|null, code?: string|null, name?: string|null} $data */
    public function fromArray(array $data): ListReportForcesInputDTO
    {
        return new ListReportForcesInputDTO(
            id: $data['id'] ?? null,
            code: $data['code'] ?? null,
            name: $data['name'] ?? null,
        );
    }

    /** @return array{forces: list<array{key: string, label: string, name: string}>} */
    public function toArray(ListReportForcesOutputDTO $data): array
    {
        return [
            'forces' => array_map(fn (array $force): array => [
                'key' => $force['code'],
                'label' => $force['code'],
                'name' => $force['name'],
            ], $data->items),
        ];
    }
}
