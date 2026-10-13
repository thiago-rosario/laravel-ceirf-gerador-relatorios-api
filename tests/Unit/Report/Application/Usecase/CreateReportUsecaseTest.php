<?php

declare(strict_types=1);

use src\Modules\Report\Application\DTO\CreateReportInputDTO;
use src\Modules\Report\Application\DTO\ReportCreationScopeDTO;
use src\Modules\Report\Application\Exception\ReportAccessDeniedException;
use src\Modules\Report\Application\Interfaces\Mapper\ReportChecklistMapperInterface;
use src\Modules\Report\Application\Interfaces\Service\ReportAccessServiceInterface;
use src\Modules\Report\Application\Usecase\CreateReportUsecase;
use src\Modules\Report\Domain\Entity\ReportEntity;
use src\Modules\Report\Domain\Enum\ChecklistAnswerEnum;
use src\Modules\Report\Domain\Enum\ReportStatusEnum;
use src\Modules\Report\Domain\Exception\InvalidReportIdException;
use src\Modules\Report\Domain\Repository\ReportRepositoryInterface;
use src\Modules\Report\Domain\ValueObject\ReportChecklistValueObject;
use src\Modules\Report\Domain\ValueObject\ReportConclusionValueObject;
use src\Modules\Report\Domain\ValueObject\ReportCoverValueObject;
use src\Modules\Report\Domain\ValueObject\ReportGeneralInformationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportInfrastructureValueObject;
use src\Modules\Report\Domain\ValueObject\ReportLocationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPhotographicDocumentationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPreImplementationValueObject;

afterEach(function (): void {
    Mockery::close();
});

function reportCreationAccess(CreateReportInputDTO $input, bool $called = true): ReportAccessServiceInterface
{
    $access = Mockery::mock(ReportAccessServiceInterface::class);

    if ($called) {
        $access->shouldReceive('creationScope')->once()->with($input->createdBy, $input->reportTypeId, $input->coordinationId)
            ->andReturn(new ReportCreationScopeDTO($input->coordinationId, $input->reportTypeId));
    } else {
        $access->shouldNotReceive('creationScope');
    }

    return $access;
}

test('creates an initial draft with unanswered checklist and absent sections', function (): void {
    $input = new CreateReportInputDTO(createdBy: '550e8400-e29b-41d4-a716-446655440000');
    $checklist = new ReportChecklistValueObject;
    $mapper = Mockery::mock(ReportChecklistMapperInterface::class);
    $mapper->shouldReceive('map')->once()->with($input)->andReturn($checklist);
    $repository = Mockery::mock(ReportRepositoryInterface::class);
    $repository->shouldReceive('insert')->once()->with(Mockery::on(
        fn (ReportEntity $report): bool => $report->createdBy()->value() === '550e8400-e29b-41d4-a716-446655440000'
            && $report->status() === ReportStatusEnum::DRAFT
            && $report->revisionNumber() === 0
            && $report->rootReportId()->value() === $report->id()->value()
            && $report->parentReportId() === null
            && $report->generatedDocument() === null
            && $report->cover() === null
            && $report->generalInformation() === null
            && $report->location() === null
            && $report->infrastructure() === null
            && $report->preImplementation() === null
            && $report->photographicDocumentation() === null
            && $report->conclusion() === null
            && $report->attachments()?->checklist() === $checklist,
    ))->andReturnUsing(fn (ReportEntity $report): ReportEntity => $report);
    $usecase = new CreateReportUsecase($repository, $mapper, reportCreationAccess($input));

    $output = $usecase($input);

    expect($output->id)->toBeUuid();
    expect($output->status)->toBe('DRAFT');
    expect($output->revisionNumber)->toBe(0);
    expect($output->createdAt)->toBeInstanceOf(DateTimeImmutable::class);
});

test('preserves supplied sections and composes the partially answered checklist from the mapper', function (): void {
    $input = new CreateReportInputDTO(
        createdBy: '550e8400-e29b-41d4-a716-446655440000',
        cover: new ReportCoverValueObject(typology: 'DEAM'),
        generalInformation: new ReportGeneralInformationValueObject(collaborators: 'Ana Silva'),
        location: new ReportLocationValueObject,
        infrastructure: new ReportInfrastructureValueObject(waterNetwork: ChecklistAnswerEnum::YES),
        preImplementation: new ReportPreImplementationValueObject,
        photographicDocumentation: new ReportPhotographicDocumentationValueObject,
        conclusion: new ReportConclusionValueObject(content: 'Parecer preliminar.'),
        stateOwnedLand: 'SIM',
        electricitySupply: 'NÃO',
        environmentalProtectionArea: 'NÃO SE APLICA',
    );
    $checklist = new ReportChecklistValueObject(
        stateOwnedLand: ChecklistAnswerEnum::YES,
        electricitySupply: ChecklistAnswerEnum::NO,
        environmentalProtectionArea: ChecklistAnswerEnum::NOT_APPLICABLE,
    );
    $mapper = Mockery::mock(ReportChecklistMapperInterface::class);
    $mapper->shouldReceive('map')->once()->with($input)->andReturn($checklist);
    $repository = Mockery::mock(ReportRepositoryInterface::class);
    $repository->shouldReceive('insert')->once()->with(Mockery::on(
        fn (ReportEntity $report): bool => $report->cover() === $input->cover
            && $report->generalInformation() === $input->generalInformation
            && $report->location() === $input->location
            && $report->infrastructure() === $input->infrastructure
            && $report->preImplementation() === $input->preImplementation
            && $report->photographicDocumentation() === $input->photographicDocumentation
            && $report->conclusion() === $input->conclusion
            && $report->attachments()?->checklist() === $checklist,
    ))->andReturnUsing(fn (ReportEntity $report): ReportEntity => $report);
    $usecase = new CreateReportUsecase($repository, $mapper, reportCreationAccess($input));

    $output = $usecase($input);

    expect($output->status)->toBe('DRAFT');
});

test('returns metadata from the report returned by the repository', function (): void {
    $input = new CreateReportInputDTO(createdBy: '550e8400-e29b-41d4-a716-446655440000');
    $savedAt = new DateTimeImmutable('2026-10-01T10:00:00+00:00');
    $savedReport = new ReportEntity(
        createdBy: '550e8400-e29b-41d4-a716-446655440000',
        id: '550e8400-e29b-41d4-a716-446655440001',
        createdAt: $savedAt,
    );
    $mapper = Mockery::mock(ReportChecklistMapperInterface::class);
    $mapper->shouldReceive('map')->once()->with($input)->andReturn(new ReportChecklistValueObject);
    $repository = Mockery::mock(ReportRepositoryInterface::class);
    $repository->shouldReceive('insert')->once()->with(Mockery::type(ReportEntity::class))->andReturn($savedReport);
    $usecase = new CreateReportUsecase($repository, $mapper, reportCreationAccess($input));

    $output = $usecase($input);

    expect(get_object_vars($output))->toBe([
        'id' => '550e8400-e29b-41d4-a716-446655440001',
        'status' => 'DRAFT',
        'revisionNumber' => 0,
        'createdAt' => $savedReport->createdAt(),
    ]);
});

test('propagates a mapper failure without persisting a report', function (): void {
    $input = new CreateReportInputDTO(createdBy: '550e8400-e29b-41d4-a716-446655440000');
    $exception = new RuntimeException('Falha ao mapear o checklist.');
    $mapper = Mockery::mock(ReportChecklistMapperInterface::class);
    $mapper->shouldReceive('map')->once()->with($input)->andThrow($exception);
    $repository = Mockery::mock(ReportRepositoryInterface::class);
    $repository->shouldNotReceive('insert');
    $usecase = new CreateReportUsecase($repository, $mapper, reportCreationAccess($input, false));

    expect(fn () => $usecase($input))->toThrow($exception);
});

test('propagates a repository failure', function (): void {
    $input = new CreateReportInputDTO(createdBy: '550e8400-e29b-41d4-a716-446655440000');
    $exception = new RuntimeException('Falha ao persistir o relatório.');
    $mapper = Mockery::mock(ReportChecklistMapperInterface::class);
    $mapper->shouldReceive('map')->once()->with($input)->andReturn(new ReportChecklistValueObject);
    $repository = Mockery::mock(ReportRepositoryInterface::class);
    $repository->shouldReceive('insert')->once()->with(Mockery::type(ReportEntity::class))->andThrow($exception);
    $usecase = new CreateReportUsecase($repository, $mapper, reportCreationAccess($input));

    expect(fn () => $usecase($input))->toThrow($exception);
});

test('rejects an invalid author identifier without persisting a report', function (): void {
    $input = new CreateReportInputDTO(createdBy: 'invalid-author-id');
    $mapper = Mockery::mock(ReportChecklistMapperInterface::class);
    $mapper->shouldReceive('map')->once()->with($input)->andReturn(new ReportChecklistValueObject);
    $repository = Mockery::mock(ReportRepositoryInterface::class);
    $repository->shouldNotReceive('insert');
    $usecase = new CreateReportUsecase($repository, $mapper, reportCreationAccess($input));

    expect(fn () => $usecase($input))->toThrow(InvalidReportIdException::class);
});

test('does not persist a report when creation authorization is refused', function (): void {
    $input = new CreateReportInputDTO(createdBy: '550e8400-e29b-41d4-a716-446655440000', coordinationId: 20);
    $mapper = Mockery::mock(ReportChecklistMapperInterface::class);
    $mapper->shouldReceive('map')->once()->with($input)->andReturn(new ReportChecklistValueObject);
    $access = Mockery::mock(ReportAccessServiceInterface::class);
    $access->shouldReceive('creationScope')->once()->with($input->createdBy, null, 20)->andThrow(new ReportAccessDeniedException);
    $repository = Mockery::mock(ReportRepositoryInterface::class);
    $repository->shouldNotReceive('insert');
    $usecase = new CreateReportUsecase($repository, $mapper, $access);

    expect(fn () => $usecase($input))->toThrow(ReportAccessDeniedException::class);
});
