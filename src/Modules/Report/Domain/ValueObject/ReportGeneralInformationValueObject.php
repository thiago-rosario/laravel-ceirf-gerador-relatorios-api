<?php

declare(strict_types=1);

namespace src\Modules\Report\Domain\ValueObject;

use DateTimeImmutable;
use src\Modules\Report\Domain\Validation\ReportGeneralInformationValidation;

readonly class ReportGeneralInformationValueObject
{
    /** @param list<ReportInstitutionalRepresentativeValueObject> $institutionalRepresentatives */
    public function __construct(
        private ?DateTimeImmutable $inspectionDate = null,
        private string $collaborators = '',
        private array $institutionalRepresentatives = [],
    ) {
        ReportGeneralInformationValidation::validateInstitutionalRepresentatives($institutionalRepresentatives);
        ReportGeneralInformationValidation::validate($this);
    }

    public function inspectionDate(): ?DateTimeImmutable
    {
        return $this->inspectionDate;
    }

    public function collaborators(): string
    {
        return $this->collaborators;
    }

    /** @return list<ReportInstitutionalRepresentativeValueObject> */
    public function institutionalRepresentatives(): array
    {
        return $this->institutionalRepresentatives;
    }
}
