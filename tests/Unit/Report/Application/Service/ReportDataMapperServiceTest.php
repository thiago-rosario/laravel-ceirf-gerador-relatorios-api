<?php

declare(strict_types=1);

use src\Modules\Report\Application\DTO\GeneratedReportDataDTO;
use src\Modules\Report\Application\DTO\MunicipalityDataDTO;
use src\Modules\Report\Application\DTO\ReportAttachmentDataDTO;
use src\Modules\Report\Application\DTO\ReportAttachmentsDataDTO;
use src\Modules\Report\Application\DTO\ReportChecklistDataDTO;
use src\Modules\Report\Application\DTO\ReportConclusionDataDTO;
use src\Modules\Report\Application\DTO\ReportCoverDataDTO;
use src\Modules\Report\Application\DTO\ReportDataDTO;
use src\Modules\Report\Application\DTO\ReportFileReferenceDataDTO;
use src\Modules\Report\Application\DTO\ReportGeneralInformationDataDTO;
use src\Modules\Report\Application\DTO\ReportImageDataDTO;
use src\Modules\Report\Application\DTO\ReportInfrastructureDataDTO;
use src\Modules\Report\Application\DTO\ReportLocationDataDTO;
use src\Modules\Report\Application\DTO\ReportPhotographicDocumentationDataDTO;
use src\Modules\Report\Application\DTO\ReportPreImplementationDataDTO;
use src\Modules\Report\Application\Service\ReportDataMapperService;
use src\Modules\Report\Domain\Entity\ReportAttachmentEntity;
use src\Modules\Report\Domain\Entity\ReportEntity;
use src\Modules\Report\Domain\Enum\ChecklistAnswerEnum;
use src\Modules\Report\Domain\Enum\ReportAttachmentTypeEnum;
use src\Modules\Report\Domain\ValueObject\ReportAttachmentsValueObject;
use src\Modules\Report\Domain\ValueObject\ReportChecklistValueObject;
use src\Modules\Report\Domain\ValueObject\ReportConclusionValueObject;
use src\Modules\Report\Domain\ValueObject\ReportCoverValueObject;
use src\Modules\Report\Domain\ValueObject\ReportGeneralInformationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportInfrastructureValueObject;
use src\Modules\Report\Domain\ValueObject\ReportLocationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPhotographicDocumentationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPreImplementationValueObject;
use Tests\Fixtures\ReportApplicationFixtures;

test('maps incomplete draft metadata and absent sections without requiring a generated filename', function (): void {
    $report = new ReportEntity(
        createdBy: '550e8400-e29b-41d4-a716-446655440000',
        id: '550e8400-e29b-41d4-a716-446655440010',
        createdAt: '2020-01-01T08:00:00+00:00',
        updatedAt: '2020-01-02T09:00:00+00:00',
    );

    $output = (new ReportDataMapperService)->map($report);

    expect($output)->toBeInstanceOf(ReportDataDTO::class);
    expect(get_object_vars($output))->toBe([
        'id' => '550e8400-e29b-41d4-a716-446655440010',
        'rootReportId' => '550e8400-e29b-41d4-a716-446655440010',
        'parentReportId' => null,
        'createdBy' => '550e8400-e29b-41d4-a716-446655440000',
        'revisionNumber' => 0,
        'status' => 'DRAFT',
        'revisionLabel' => null,
        'isGenerated' => false,
        'isRevision' => false,
        'createdAt' => $report->createdAt(),
        'updatedAt' => $report->updatedAt(),
        'cover' => null,
        'generalInformation' => null,
        'location' => null,
        'infrastructure' => null,
        'preImplementation' => null,
        'photographicDocumentation' => null,
        'attachments' => null,
        'conclusion' => null,
        'generatedDocument' => null,
        'uploadedImages' => [],
        'coordinationId' => null,
        'reportTypeId' => null,
    ]);
    expect($output->createdAt->format(DateTimeInterface::ATOM))->toBe('2020-01-01T08:00:00+00:00');
    expect($output->updatedAt->format(DateTimeInterface::ATOM))->toBe('2020-01-02T09:00:00+00:00');
});

test('preserves present empty sections and their unanswered optional fields', function (): void {
    $report = new ReportEntity(
        createdBy: '550e8400-e29b-41d4-a716-446655440000',
        id: '550e8400-e29b-41d4-a716-446655440010',
        createdAt: '2020-01-01 08:00:00',
        cover: new ReportCoverValueObject,
        generalInformation: new ReportGeneralInformationValueObject,
        location: new ReportLocationValueObject,
        infrastructure: new ReportInfrastructureValueObject(waterNetwork: ChecklistAnswerEnum::NO),
        preImplementation: new ReportPreImplementationValueObject,
        photographicDocumentation: new ReportPhotographicDocumentationValueObject,
        attachments: new ReportAttachmentsValueObject(checklist: new ReportChecklistValueObject(
            stateOwnedLand: ChecklistAnswerEnum::YES,
            floodHistory: ChecklistAnswerEnum::NOT_APPLICABLE,
        )),
        conclusion: new ReportConclusionValueObject,
    );

    $output = (new ReportDataMapperService)->map($report);

    expect($output->cover)->toEqual(new ReportCoverDataDTO(null, null, null, '', null));
    expect($output->generalInformation)->toEqual(new ReportGeneralInformationDataDTO(null, ''));
    expect($output->location)->toEqual(new ReportLocationDataDTO(null, null));
    expect($output->infrastructure)->toEqual(new ReportInfrastructureDataDTO('NÃO', null, null, null, null, null, null, null, null, null));
    expect($output->preImplementation)->toEqual(new ReportPreImplementationDataDTO(null));
    expect($output->photographicDocumentation)->toEqual(new ReportPhotographicDocumentationDataDTO([]));
    expect($output->attachments)->toEqual(new ReportAttachmentsDataDTO(
        new ReportChecklistDataDTO(null, null, 'SIM', null, null, null, null, 'NÃO SE APLICA', null, null, null, null, null, null, null, null, null, null, null, null),
        null,
        null,
        [],
    ));
    expect($output->conclusion)->toEqual(new ReportConclusionDataDTO(''));
});

test('maps generated content to typed nested DTOs while preserving media order and removed upload history', function (): void {
    $report = ReportApplicationFixtures::completeReport([
        'location' => new ReportLocationValueObject(
            locationMap: ReportApplicationFixtures::image(1),
            municipalityInStateMap: ReportApplicationFixtures::image(2),
        ),
        'preImplementation' => new ReportPreImplementationValueObject(ReportApplicationFixtures::image(3)),
        'photographicDocumentation' => new ReportPhotographicDocumentationValueObject([
            ReportApplicationFixtures::image(4),
            ReportApplicationFixtures::image(5),
        ]),
        'attachments' => new ReportAttachmentsValueObject(
            checklist: new ReportChecklistValueObject(
                seiConstructionRequest: ChecklistAnswerEnum::YES,
                seiLandAndTypologyIdentification: ChecklistAnswerEnum::NO,
                stateOwnedLand: ChecklistAnswerEnum::NOT_APPLICABLE,
                simovLegalized: ChecklistAnswerEnum::YES,
                compatibleDimensions: ChecklistAnswerEnum::NO,
                slopeOrLevelRisk: ChecklistAnswerEnum::NOT_APPLICABLE,
                stormwaterDrainage: ChecklistAnswerEnum::YES,
                floodHistory: ChecklistAnswerEnum::NO,
                electricitySupply: ChecklistAnswerEnum::NOT_APPLICABLE,
                waterSupply: ChecklistAnswerEnum::YES,
                sewageSupply: ChecklistAnswerEnum::NO,
                pavingAndSidewalk: ChecklistAnswerEnum::NOT_APPLICABLE,
                regularWasteCollection: ChecklistAnswerEnum::YES,
                demolitionRequired: ChecklistAnswerEnum::NO,
                easyPublicAccess: ChecklistAnswerEnum::NOT_APPLICABLE,
                domainStripOrNonBuildableArea: ChecklistAnswerEnum::YES,
                technicalFeasibilityReport: ChecklistAnswerEnum::NO,
                reportAttachedToSei: ChecklistAnswerEnum::NOT_APPLICABLE,
                worksDashboardUpdated: ChecklistAnswerEnum::YES,
                environmentalProtectionArea: ChecklistAnswerEnum::NO,
            ),
            municipalityLocationMap: new ReportAttachmentEntity(
                ReportAttachmentTypeEnum::MUNICIPALITY_LOCATION_MAP,
                ReportApplicationFixtures::image(6)->file(),
                'Mapa municipal',
                '550e8400-e29b-41d4-a716-446655440006',
            ),
            topographicPlan: new ReportAttachmentEntity(
                ReportAttachmentTypeEnum::TOPOGRAPHIC_PLAN,
                ReportApplicationFixtures::image(7)->file(),
                'Planta topográfica',
                '550e8400-e29b-41d4-a716-446655440007',
            ),
            others: [new ReportAttachmentEntity(
                ReportAttachmentTypeEnum::OTHER,
                ReportApplicationFixtures::image(8)->file(),
                'Levantamento complementar',
                '550e8400-e29b-41d4-a716-446655440008',
            )],
        ),
        'generatedDocument' => ReportApplicationFixtures::document('SALVADOR_PM_1B_DELEGACIA.pdf'),
        'uploadedImages' => [ReportApplicationFixtures::image(9)],
    ]);

    $output = (new ReportDataMapperService)->map($report);

    expect($output->status)->toBe('GENERATED');
    expect($output->isGenerated)->toBeTrue();
    expect($output->cover)->toEqual(new ReportCoverDataDTO(
        new MunicipalityDataDTO(1, 'Salvador', 'BA'),
        'PM',
        '1B',
        'Delegacia',
        '012.3456.2020.0000012-34',
    ));
    expect($output->generalInformation)->toEqual(new ReportGeneralInformationDataDTO(
        new DateTimeImmutable('2020-01-01'),
        'Ana Silva e João Santos',
    ));
    expect($output->infrastructure)->toEqual(new ReportInfrastructureDataDTO(
        'SIM', 'NÃO', 'SIM', 'NÃO', 'NÃO SE APLICA', 'SIM', 'SIM', 'SIM', 'NÃO', 'NÃO',
    ));
    expect($output->attachments?->checklist)->toEqual(new ReportChecklistDataDTO(
        'SIM', 'NÃO', 'NÃO SE APLICA', 'SIM', 'NÃO', 'NÃO SE APLICA', 'SIM', 'NÃO', 'NÃO SE APLICA', 'SIM',
        'NÃO', 'NÃO SE APLICA', 'SIM', 'NÃO', 'NÃO SE APLICA', 'SIM', 'NÃO', 'NÃO SE APLICA', 'SIM', 'NÃO',
    ));
    expect($output->conclusion)->toEqual(new ReportConclusionDataDTO('O terreno apresenta condições adequadas à implantação.'));
    expect($output->generatedDocument)->toEqual(new GeneratedReportDataDTO(
        'document:final',
        'SALVADOR_PM_1B_DELEGACIA.pdf',
        new DateTimeImmutable('2020-01-01 10:00:00'),
    ));
    expect($output->location)->toBeInstanceOf(ReportLocationDataDTO::class);
    expect($output->location?->locationMap)->toBeInstanceOf(ReportImageDataDTO::class);
    expect($output->location?->locationMap?->id)->toBe('550e8400-e29b-41d4-a716-000000000001');
    expect($output->location?->municipalityInStateMap?->id)->toBe('550e8400-e29b-41d4-a716-000000000002');
    expect($output->preImplementation?->image?->id)->toBe('550e8400-e29b-41d4-a716-000000000003');
    expect($output->photographicDocumentation?->images)->toContainOnlyInstancesOf(ReportImageDataDTO::class);
    expect(array_column($output->photographicDocumentation?->images ?? [], 'order'))->toBe([4, 5]);
    expect($output->photographicDocumentation?->images[0]->caption)->toBe('Figura do terreno 4');
    expect($output->photographicDocumentation?->images[0]->file)->toEqual(new ReportFileReferenceDataDTO(
        'asset:4', 'figure-4.png', 'image/png', 1024, hash('sha256', 'figure-content-4'),
    ));
    expect($output->attachments?->municipalityLocationMap)->toEqual(new ReportAttachmentDataDTO(
        '550e8400-e29b-41d4-a716-446655440006',
        'MUNICIPALITY_LOCATION_MAP',
        new ReportFileReferenceDataDTO('asset:6', 'figure-6.png', 'image/png', 1024, hash('sha256', 'figure-content-6')),
        'Mapa municipal',
    ));
    expect($output->attachments?->topographicPlan?->type)->toBe('TOPOGRAPHIC_PLAN');
    expect($output->attachments?->topographicPlan?->description)->toBe('Planta topográfica');
    expect($output->attachments?->others)->toContainOnlyInstancesOf(ReportAttachmentDataDTO::class);
    expect($output->attachments?->others[0]->type)->toBe('OTHER');
    expect($output->attachments?->others[0]->description)->toBe('Levantamento complementar');
    expect($output->uploadedImages)->toContainOnlyInstancesOf(ReportImageDataDTO::class);
    expect(array_column($output->uploadedImages, 'order'))->toBe([9, 1, 2, 3, 4, 5]);
});

test('preserves family metadata when mapping an editable documentary revision', function (): void {
    $report = new ReportEntity(
        createdBy: '550e8400-e29b-41d4-a716-446655440001',
        id: '550e8400-e29b-41d4-a716-446655440011',
        rootReportId: '550e8400-e29b-41d4-a716-446655440010',
        parentReportId: '550e8400-e29b-41d4-a716-446655440010',
        revisionNumber: 1,
        createdAt: '2020-01-01 08:00:00',
    );

    $output = (new ReportDataMapperService)->map($report);

    expect($output->id)->toBe('550e8400-e29b-41d4-a716-446655440011');
    expect($output->rootReportId)->toBe('550e8400-e29b-41d4-a716-446655440010');
    expect($output->parentReportId)->toBe('550e8400-e29b-41d4-a716-446655440010');
    expect($output->createdBy)->toBe('550e8400-e29b-41d4-a716-446655440001');
    expect($output->revisionNumber)->toBe(1);
    expect($output->revisionLabel)->toBe('REV1');
    expect($output->isRevision)->toBeTrue();
    expect($output->status)->toBe('DRAFT');
    expect($output->isGenerated)->toBeFalse();
    expect($output->generatedDocument)->toBeNull();
});

test('preserves an attachment section without an optional checklist', function (): void {
    $report = new ReportEntity(
        createdBy: '550e8400-e29b-41d4-a716-446655440000',
        id: '550e8400-e29b-41d4-a716-446655440010',
        createdAt: '2020-01-01 08:00:00',
        attachments: new ReportAttachmentsValueObject,
    );

    $output = (new ReportDataMapperService)->map($report);

    expect($output->attachments)->toEqual(new ReportAttachmentsDataDTO(null, null, null, []));
});
