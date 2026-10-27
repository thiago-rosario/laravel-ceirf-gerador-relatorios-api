<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\DTO;

readonly class SearchReportsInputDTO
{
    public function __construct(
        public string $actorId,
        public ?int $page = null,
        public int $perPage = 10,
        public ?int $municipalityId = null,
        public ?string $typology = null,
        public ?string $userId = null,
        public ?int $coordinationId = null,
        public ?string $rootReportId = null,
        public ?string $month = null,
        public ?string $createdBy = null,
        public bool $countOnly = false,
        public bool $includeAuthors = false,
    ) {}
}
