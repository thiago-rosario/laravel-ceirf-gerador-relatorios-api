<?php

declare(strict_types=1);

use src\Modules\Report\Application\Service\ReportDataMapperService;
use src\Modules\Report\Domain\Entity\ReportAttachmentEntity;
use src\Modules\Report\Domain\Entity\ReportEntity;
use src\Modules\Report\Domain\Entity\ReportImageEntity;
use src\Modules\Report\Domain\Enum\ChecklistAnswerEnum;
use src\Modules\Report\Domain\Enum\ReportAttachmentTypeEnum;
use src\Modules\Report\Domain\Exception\InvalidReportImageOrderException;
use src\Modules\Report\Domain\Exception\ReportAlreadyGeneratedException;
use src\Modules\Report\Domain\ValueObject\ReportAttachmentsValueObject;
use src\Modules\Report\Domain\ValueObject\ReportChecklistValueObject;
use src\Modules\Report\Domain\ValueObject\ReportConclusionValueObject;
use src\Modules\Report\Domain\ValueObject\ReportCoverValueObject;
use src\Modules\Report\Domain\ValueObject\ReportGeneralInformationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportInfrastructureValueObject;
use src\Modules\Report\Domain\ValueObject\ReportInstitutionalRepresentativeValueObject;
use src\Modules\Report\Domain\ValueObject\ReportLocationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPhotographicDocumentationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPreImplementationValueObject;
use src\Modules\Report\Infra\Mapper\ReportPersistenceMapper;
use Tests\Fixtures\ReportApplicationFixtures;

test('restores multiple institutional representatives and their positions after JSON storage', function (): void {
    $report = new ReportEntity(
        createdBy: '550e8400-e29b-41d4-a716-446655440000',
        generalInformation: new ReportGeneralInformationValueObject(
            inspectionDate: new DateTimeImmutable('2020-01-01'),
            collaborators: 'Equipe técnica',
            institutionalRepresentatives: [
                new ReportInstitutionalRepresentativeValueObject('Ana Souza', 'Secretária'),
                new ReportInstitutionalRepresentativeValueObject('José Santos', 'Diretor'),
            ],
        ),
    );
    $mapper = new ReportPersistenceMapper;

    $payload = json_decode(json_encode($mapper->toPayload($report), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    $restored = $mapper->fromPayload($payload);
    $information = (new ReportDataMapperService)->map($restored)->generalInformation;

    expect($payload['generalInformation']['institutionalRepresentatives'])->toBe([
        ['name' => 'Ana Souza', 'position' => 'Secretária'],
        ['name' => 'José Santos', 'position' => 'Diretor'],
    ]);
    expect($information?->collaborators)->toBe('Equipe técnica');
    expect($information?->inspectionDate?->format('Y-m-d'))->toBe('2020-01-01');
    expect($information?->institutionalRepresentatives)->toHaveCount(2);
    expect($information?->institutionalRepresentatives[0]->name)->toBe('Ana Souza');
    expect($information?->institutionalRepresentatives[0]->position)->toBe('Secretária');
    expect($information?->institutionalRepresentatives[1]->name)->toBe('José Santos');
    expect($information?->institutionalRepresentatives[1]->position)->toBe('Diretor');
});

test('restores older generated snapshots without institutional representatives', function (): void {
    $original = ReportApplicationFixtures::generatedReport();
    $mapper = new ReportPersistenceMapper;
    $payload = $mapper->toPayload($original);
    unset($payload['generalInformation']['institutionalRepresentatives']);

    $restored = $mapper->fromPayload($payload);

    expect($restored->generalInformation()?->institutionalRepresentatives())->toBe([]);
    expect($restored->generalInformation()?->collaborators())->toBe('Ana Silva e João Santos');
    expect($restored->generalInformation()?->inspectionDate()?->format('Y-m-d'))->toBe('2020-01-01');
    expect($restored->isGenerated())->toBeTrue();
    expect($restored->generatedDocument()?->fileName())->toBe('SALVADOR_PM_1B_DELEGACIA.pdf');
});

test('restores absent draft sections and preserves precise dates after JSON storage', function (): void {
    $report = new ReportEntity(
        createdBy: '550e8400-e29b-41d4-a716-446655440000',
        id: '550e8400-e29b-41d4-a716-446655440010',
        createdAt: '2020-01-01T08:00:00.123456-03:00',
        updatedAt: '2020-01-02T09:00:00.987654-03:00',
    );
    $mapper = new ReportPersistenceMapper;

    $payload = json_decode(json_encode($mapper->toPayload($report), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    $restored = $mapper->fromPayload($payload);

    expect((new ReportDataMapperService)->map($restored))->toEqual((new ReportDataMapperService)->map($report));
    expect($restored->createdAt()->format('Y-m-d\TH:i:s.uP'))->toBe('2020-01-01T08:00:00.123456-03:00');
    expect($restored->updatedAt()->format('Y-m-d\TH:i:s.uP'))->toBe('2020-01-02T09:00:00.987654-03:00');
    expect($restored->cover())->toBeNull();
    expect($restored->photographicDocumentation())->toBeNull();
    expect($restored->attachments())->toBeNull();
});

test('preserves present empty sections and nullable checklist answers', function (): void {
    $report = new ReportEntity(
        createdBy: '550e8400-e29b-41d4-a716-446655440000',
        id: '550e8400-e29b-41d4-a716-446655440010',
        createdAt: '2020-01-01 08:00:00',
        cover: new ReportCoverValueObject,
        generalInformation: new ReportGeneralInformationValueObject,
        location: new ReportLocationValueObject,
        infrastructure: new ReportInfrastructureValueObject,
        preImplementation: new ReportPreImplementationValueObject,
        photographicDocumentation: new ReportPhotographicDocumentationValueObject,
        attachments: new ReportAttachmentsValueObject(checklist: new ReportChecklistValueObject),
        conclusion: new ReportConclusionValueObject,
    );
    $mapper = new ReportPersistenceMapper;

    $restored = $mapper->fromPayload($mapper->toPayload($report));

    expect((new ReportDataMapperService)->map($restored))->toEqual((new ReportDataMapperService)->map($report));
    expect($restored->cover()?->typology())->toBe('');
    expect($restored->generalInformation()?->inspectionDate())->toBeNull();
    expect($restored->location())->toBeInstanceOf(ReportLocationValueObject::class);
    expect($restored->preImplementation())->toBeInstanceOf(ReportPreImplementationValueObject::class);
    expect($restored->photographicDocumentation()?->images())->toBe([]);
    expect($restored->attachments()?->checklist())->toBeInstanceOf(ReportChecklistValueObject::class);
});

test('restores generated documents with municipality snapshots and distinct current and historical media', function (): void {
    $checklist = ReportApplicationFixtures::completeReport()->attachments()?->checklist();
    $report = ReportApplicationFixtures::completeReport([
        'id' => '550e8400-e29b-41d4-a716-446655440010',
        'createdAt' => '2020-01-01 08:00:00',
        'updatedAt' => '2020-01-02 09:00:00',
        'location' => new ReportLocationValueObject(
            locationMap: ReportApplicationFixtures::image(1),
            municipalityInStateMap: ReportApplicationFixtures::image(2),
        ),
        'preImplementation' => new ReportPreImplementationValueObject(ReportApplicationFixtures::image(3)),
        'photographicDocumentation' => new ReportPhotographicDocumentationValueObject([
            ReportApplicationFixtures::image(4)->withCaption('Updated caption'),
            ReportApplicationFixtures::image(5),
        ]),
        'attachments' => new ReportAttachmentsValueObject(
            checklist: $checklist,
            municipalityLocationMap: new ReportAttachmentEntity(
                type: ReportAttachmentTypeEnum::MUNICIPALITY_LOCATION_MAP,
                file: ReportApplicationFixtures::image(6)->file(),
                id: '550e8400-e29b-41d4-a716-446655440006',
            ),
            topographicPlan: new ReportAttachmentEntity(
                type: ReportAttachmentTypeEnum::TOPOGRAPHIC_PLAN,
                file: ReportApplicationFixtures::image(7)->file(),
                id: '550e8400-e29b-41d4-a716-446655440007',
            ),
            others: [new ReportAttachmentEntity(
                type: ReportAttachmentTypeEnum::OTHER,
                file: ReportApplicationFixtures::image(8)->file(),
                description: 'Additional survey',
                id: '550e8400-e29b-41d4-a716-446655440008',
            )],
        ),
        'generatedDocument' => ReportApplicationFixtures::document('SALVADOR_PM_1B_DELEGACIA.pdf'),
        'uploadedImages' => [ReportApplicationFixtures::image(9), ReportApplicationFixtures::image(4)],
    ]);
    $mapper = new ReportPersistenceMapper;

    $payload = json_decode(json_encode($mapper->toPayload($report), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    $restored = $mapper->fromPayload($payload);

    expect((new ReportDataMapperService)->map($restored))->toEqual((new ReportDataMapperService)->map($report));
    expect($restored->fileName())->toBe('SALVADOR_PM_1B_DELEGACIA.pdf');
    expect($restored->cover()?->municipality()?->name())->toBe('Salvador');
    expect($restored->photographicDocumentation()?->images()[0]->caption())->toBe('Updated caption');
    expect(array_map(fn ($image): int => $image->order(), $restored->uploadedImages()))->toBe([9, 4, 1, 2, 3, 5]);
    expect($restored->uploadedImages()[1]->caption())->toBe('Figura do terreno 4');
    expect(fn () => $restored->changeConclusion(new ReportConclusionValueObject('Changed')))->toThrow(ReportAlreadyGeneratedException::class);
});

test('restores revision family references without carrying forward the generated document', function (): void {
    $report = ReportApplicationFixtures::generatedReport()->createRevision('550e8400-e29b-41d4-a716-446655440001');
    $mapper = new ReportPersistenceMapper;

    $restored = $mapper->fromPayload($mapper->toPayload($report));

    expect($restored->id()->value())->toBe($report->id()->value());
    expect($restored->rootReportId()->value())->toBe($report->rootReportId()->value());
    expect($restored->parentReportId()?->value())->toBe($report->parentReportId()?->value());
    expect($restored->createdBy()->value())->toBe('550e8400-e29b-41d4-a716-446655440001');
    expect($restored->revisionNumber())->toBe(1);
    expect($restored->isGenerated())->toBeFalse();
    expect($restored->generatedDocument())->toBeNull();
});

test('retains removed uploads to prevent reordering after draft restoration', function (): void {
    $report = new ReportEntity(
        createdBy: '550e8400-e29b-41d4-a716-446655440000',
        id: '550e8400-e29b-41d4-a716-446655440010',
        createdAt: '2020-01-01 08:00:00',
        photographicDocumentation: new ReportPhotographicDocumentationValueObject([ReportApplicationFixtures::image(1)]),
    );
    $report->changePhotographicDocumentation(new ReportPhotographicDocumentationValueObject);
    $mapper = new ReportPersistenceMapper;

    $restored = $mapper->fromPayload($mapper->toPayload($report));
    $uploadedImage = $restored->uploadedImages()[0];
    $reorderedImage = new ReportImageEntity(
        file: $uploadedImage->file(),
        order: 2,
        id: $uploadedImage->id(),
    );

    expect($restored->photographicDocumentation()?->images())->toBe([]);
    expect(fn () => $restored->changePhotographicDocumentation(new ReportPhotographicDocumentationValueObject([$reorderedImage])))
        ->toThrow(InvalidReportImageOrderException::class);
});

test('projects scalar columns with separate codes for negative unanswered and not applicable answers', function (): void {
    $report = ReportApplicationFixtures::completeReport([
        'infrastructure' => new ReportInfrastructureValueObject(
            waterNetwork: ChecklistAnswerEnum::YES,
            highVoltageNetwork: ChecklistAnswerEnum::NO,
            telephony: ChecklistAnswerEnum::NOT_APPLICABLE,
        ),
        'attachments' => new ReportAttachmentsValueObject(checklist: new ReportChecklistValueObject(
            seiConstructionRequest: ChecklistAnswerEnum::YES,
            stateOwnedLand: ChecklistAnswerEnum::NO,
            environmentalProtectionArea: ChecklistAnswerEnum::NOT_APPLICABLE,
        )),
    ]);

    $attributes = (new ReportPersistenceMapper)->toAttributes($report);

    expect($attributes['typology'])->toBe('Delegacia');
    expect($attributes['sei_number'])->toBe('012.3456.2020.0000012-34');
    expect($attributes['inspection_date'])->toBe('2020-01-01');
    expect($attributes['infrastructure_water_network'])->toBe(1);
    expect($attributes['infrastructure_high_voltage_network'])->toBe(0);
    expect($attributes['infrastructure_telephony'])->toBe(2);
    expect($attributes['infrastructure_low_voltage_network'])->toBeNull();
    expect($attributes['checklist_sei_construction_request'])->toBe(1);
    expect($attributes['checklist_state_owned_land'])->toBe(0);
    expect($attributes['checklist_environmental_protection_area'])->toBe(2);
    expect($attributes['checklist_flood_history'])->toBeNull();
});
