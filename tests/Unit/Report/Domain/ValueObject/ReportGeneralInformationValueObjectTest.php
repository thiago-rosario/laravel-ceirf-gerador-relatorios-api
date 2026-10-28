<?php

declare(strict_types=1);

use src\Modules\Report\Domain\Exception\FutureInspectionDateException;
use src\Modules\Report\Domain\Exception\IncompleteReportException;
use src\Modules\Report\Domain\Exception\InvalidInstitutionalRepresentativeException;
use src\Modules\Report\Domain\Exception\InvalidReportCollaboratorsException;
use src\Modules\Report\Domain\Validation\ReportGeneralInformationValidation;
use src\Modules\Report\Domain\ValueObject\ReportGeneralInformationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportInstitutionalRepresentativeValueObject;

test('allows pending inspection information while the report is a draft', function (): void {
    $information = new ReportGeneralInformationValueObject;

    expect($information->inspectionDate())->toBeNull();
    expect($information->collaborators())->toBe('');
    expect($information->institutionalRepresentatives())->toBe([]);
});

test('keeps institutional representatives independent from the collaborators required for generation', function (): void {
    $representative = new ReportInstitutionalRepresentativeValueObject('Ana Souza', 'Secretária');
    $information = new ReportGeneralInformationValueObject(
        inspectionDate: new DateTimeImmutable('2000-01-01'),
        institutionalRepresentatives: [$representative],
    );

    expect(fn () => ReportGeneralInformationValidation::validateForGeneration($information))
        ->toThrow(IncompleteReportException::class);

    expect($information->institutionalRepresentatives())->toBe([$representative]);
    expect($information->collaborators())->toBe('');
});

test('rejects institutional representative collections that cannot form a valid report snapshot', function (Closure $representatives): void {
    expect(fn () => new ReportGeneralInformationValueObject(institutionalRepresentatives: $representatives()))
        ->toThrow(InvalidInstitutionalRepresentativeException::class);
})->with([
    'non-list collection' => [fn (): array => [1 => new ReportInstitutionalRepresentativeValueObject('Ana Souza', 'Secretária')]],
    'unvalidated representative data' => [fn (): array => [['name' => 'Ana Souza', 'position' => 'Secretária']]],
]);

test('accepts an immutable inspection date and collaborators for generation', function (): void {
    $inspectionDate = new DateTimeImmutable('2000-01-01 10:00:00');
    $information = new ReportGeneralInformationValueObject($inspectionDate, 'Ana e José');

    ReportGeneralInformationValidation::validateForGeneration($information);

    expect($information->inspectionDate())->toBe($inspectionDate);
    expect($information->collaborators())->toBe('Ana e José');
});

test('rejects inspection dates in the future at the moment of editing', function (): void {
    $futureDate = new DateTimeImmutable('+1 day');

    expect(fn () => new ReportGeneralInformationValueObject($futureDate))
        ->toThrow(function (FutureInspectionDateException $exception): void {
            expect($exception->getCode())->toBe(2009);
            expect($exception->getMessage())->toBe('A data da vistoria não pode ser futura em relação ao momento da edição.');
        });
});

test('accepts exactly 1000 multibyte characters in the collaborator list', function (): void {
    $collaborators = str_repeat('á', 1000);

    $information = new ReportGeneralInformationValueObject(collaborators: $collaborators);

    expect($information->collaborators())->toBe($collaborators);
});

test('rejects more than 1000 collaborator characters even in a draft', function (): void {
    expect(fn () => new ReportGeneralInformationValueObject(collaborators: str_repeat('á', 1001)))
        ->toThrow(function (InvalidReportCollaboratorsException $exception): void {
            expect($exception->getCode())->toBe(2010);
            expect($exception->getMessage())->toBe('Os colaboradores devem possuir no máximo 1000 caracteres.');
        });
});

test('rejects generation while the inspection date is missing', function (): void {
    $information = new ReportGeneralInformationValueObject(collaborators: 'Ana');

    expect(fn () => ReportGeneralInformationValidation::validateForGeneration($information))
        ->toThrow(function (IncompleteReportException $exception): void {
            expect($exception->getCode())->toBe(2005);
            expect($exception->getMessage())->toBe('A data da vistoria e os colaboradores presentes devem ser informados para gerar o relatório.');
        });
});

test('rejects generation while the collaborators are missing', function (string $collaborators): void {
    $information = new ReportGeneralInformationValueObject(new DateTimeImmutable('2000-01-01'), $collaborators);

    expect(fn () => ReportGeneralInformationValidation::validateForGeneration($information))
        ->toThrow(IncompleteReportException::class);
})->with([
    'empty' => [''],
    'spaces' => ['   '],
    'Unicode whitespace' => ["\u{2003}"],
]);
