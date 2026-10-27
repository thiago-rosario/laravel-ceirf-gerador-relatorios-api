<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\DTO;

readonly class SearchReportsOutputDTO
{
    /** @param list<ReportDataDTO> $reports
     * @param  array{current_page: int, per_page: int, total: int, last_page: int, from: int|null, to: int|null}|null  $pagination
     * @param  array<string, array{id: string, name: string}|null>  $authorsByReportId
     */
    public function __construct(
        public array $reports,
        public int $total,
        public ?array $pagination = null,
        public array $authorsByReportId = [],
    ) {}
}
