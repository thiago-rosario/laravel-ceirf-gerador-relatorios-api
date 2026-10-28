<?php

declare(strict_types=1);

namespace src\Modules\Report\Domain\Validation;

use DateTimeImmutable;
use src\Modules\Report\Domain\Exception\FutureInspectionDateException;
use src\Modules\Report\Domain\Exception\IncompleteReportException;
use src\Modules\Report\Domain\Exception\InvalidInstitutionalRepresentativeException;
use src\Modules\Report\Domain\Exception\InvalidReportCollaboratorsException;
use src\Modules\Report\Domain\ValueObject\ReportGeneralInformationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportInstitutionalRepresentativeValueObject;

final class ReportGeneralInformationValidation
{
    public static function validate(ReportGeneralInformationValueObject $generalInformation): void
    {
        if ($generalInformation->inspectionDate() !== null
            && $generalInformation->inspectionDate() > new DateTimeImmutable) {
            throw new FutureInspectionDateException;
        }

        if (mb_strlen($generalInformation->collaborators()) > 1000) {
            throw new InvalidReportCollaboratorsException;
        }
    }

    public static function validateForGeneration(ReportGeneralInformationValueObject $generalInformation): void
    {
        self::validate($generalInformation);

        if ($generalInformation->inspectionDate() === null
            || ReportTextValidation::isBlank($generalInformation->collaborators())) {
            throw new IncompleteReportException('A data da vistoria e os colaboradores presentes devem ser informados para gerar o relatório.');
        }
    }

    public static function validateInstitutionalRepresentative(ReportInstitutionalRepresentativeValueObject $representative): void
    {
        if (ReportTextValidation::isBlank($representative->name())
            || ReportTextValidation::isBlank($representative->position())
            || mb_strlen($representative->name()) > 255
            || mb_strlen($representative->position()) > 255) {
            throw new InvalidInstitutionalRepresentativeException;
        }
    }

    /** @param array<array-key, mixed> $representatives */
    public static function validateInstitutionalRepresentatives(array $representatives): void
    {
        if (! array_is_list($representatives)) {
            throw new InvalidInstitutionalRepresentativeException;
        }

        foreach ($representatives as $representative) {
            if (! $representative instanceof ReportInstitutionalRepresentativeValueObject) {
                throw new InvalidInstitutionalRepresentativeException;
            }
        }
    }
}
