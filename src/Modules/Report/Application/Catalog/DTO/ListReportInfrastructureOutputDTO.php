<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Catalog\DTO;

readonly class ListReportInfrastructureOutputDTO
{
    /** @param list<array{code: string, name: string}> $items */
    public function __construct(
        public array $items,
    ) {}
}
