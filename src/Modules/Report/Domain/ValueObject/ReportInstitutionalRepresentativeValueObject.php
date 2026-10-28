<?php

declare(strict_types=1);

namespace src\Modules\Report\Domain\ValueObject;

use src\Modules\Report\Domain\Validation\ReportGeneralInformationValidation;

readonly class ReportInstitutionalRepresentativeValueObject
{
    public function __construct(
        private string $name,
        private string $position,
    ) {
        ReportGeneralInformationValidation::validateInstitutionalRepresentative($this);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function position(): string
    {
        return $this->position;
    }
}
