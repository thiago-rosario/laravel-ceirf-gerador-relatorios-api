<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\DTO;

readonly class SearchReportsOutputDTO
{
    /** @param list<ReportDataDTO> $reports */
    public function __construct(public array $reports, public int $total) {}
}
