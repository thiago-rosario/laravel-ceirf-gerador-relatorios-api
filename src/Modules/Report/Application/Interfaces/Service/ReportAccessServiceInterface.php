<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Interfaces\Service;

use src\Modules\Report\Application\DTO\ReportCreationScopeDTO;

interface ReportAccessServiceInterface
{
    public function canRead(string $userId, string $reportId): bool;

    public function canWrite(string $userId, string $reportId): bool;

    /**
     * @param  list<string>  $reportIds
     * @return list<string>
     */
    public function editorialReportIds(string $userId, array $reportIds): array;

    public function creationScope(string $userId, ?int $reportTypeId = null, ?int $coordinationId = null): ReportCreationScopeDTO;
}
