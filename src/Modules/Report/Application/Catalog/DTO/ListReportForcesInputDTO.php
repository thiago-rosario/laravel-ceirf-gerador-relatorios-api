<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Catalog\DTO;

readonly class ListReportForcesInputDTO
{
    public function __construct(
        public ?string $id = null,
        public ?string $code = null,
        public ?string $name = null,
    ) {}
}
