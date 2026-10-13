<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\DTO;

readonly class ReportCreationScopeDTO
{
    public function __construct(public ?int $coordinationId, public ?int $reportTypeId) {}
}
