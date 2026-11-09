<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Catalog\DTO;

readonly class ListReportForcesOutputDTO
{
    /** @param list<array{id: int, code: string, name: string}> $items */
    public function __construct(
        public array $items,
    ) {}
}
