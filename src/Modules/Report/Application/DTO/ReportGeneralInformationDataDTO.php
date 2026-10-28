<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\DTO;

use DateTimeImmutable;

readonly class ReportGeneralInformationDataDTO
{
    /** @param list<ReportInstitutionalRepresentativeDataDTO> $institutionalRepresentatives */
    public function __construct(
        public ?DateTimeImmutable $inspectionDate,
        public string $collaborators,
        public array $institutionalRepresentatives = [],
    ) {}
}
