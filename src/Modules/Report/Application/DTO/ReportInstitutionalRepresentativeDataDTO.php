<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\DTO;

readonly class ReportInstitutionalRepresentativeDataDTO
{
    public function __construct(
        public string $name,
        public string $position,
    ) {}
}
