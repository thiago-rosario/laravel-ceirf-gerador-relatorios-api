<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\DTO;

readonly class SearchReportsInputDTO
{
    public function __construct(
        public string $actorId,
        public ?int $municipalityId = null,
        public ?string $userId = null,
        public ?int $coordinationId = null,
        public ?string $rootReportId = null,
        public ?string $month = null,
        public bool $countOnly = false,
    ) {}
}
