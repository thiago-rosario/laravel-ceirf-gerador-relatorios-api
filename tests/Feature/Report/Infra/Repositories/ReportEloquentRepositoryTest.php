<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use src\Modules\Identity\Model\User;
use src\Modules\Report\Application\Exception\ReportNotFoundException;
use src\Modules\Report\Application\Service\ReportDataMapperService;
use src\Modules\Report\Domain\Entity\ReportAttachmentEntity;
use src\Modules\Report\Domain\Entity\ReportEntity;
use src\Modules\Report\Domain\Entity\ReportImageEntity;
use src\Modules\Report\Domain\Enum\ReportAttachmentTypeEnum;
use src\Modules\Report\Domain\Exception\InvalidReportImageOrderException;
use src\Modules\Report\Domain\Exception\InvalidReportRevisionException;
use src\Modules\Report\Domain\Exception\ReportAlreadyGeneratedException;
use src\Modules\Report\Domain\Exception\ReportNotGeneratedException;
use src\Modules\Report\Domain\Repository\ReportRepositoryInterface;
use src\Modules\Report\Domain\ValueObject\GeneratedReportValueObject;
use src\Modules\Report\Domain\ValueObject\ReportAttachmentsValueObject;
use src\Modules\Report\Domain\ValueObject\ReportConclusionValueObject;
use src\Modules\Report\Domain\ValueObject\ReportCoverValueObject;
use src\Modules\Report\Domain\ValueObject\ReportGeneralInformationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportInstitutionalRepresentativeValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPhotographicDocumentationValueObject;
use src\Modules\Report\Model\Report;
use Tests\Fixtures\ReportApplicationFixtures;

function createReportRepositoryCatalog(): void
{
    $territoryId = DB::table('identity_territories')->insertGetId(['name' => 'Salvador']);
    DB::table('municipalities')->insert([
        'id' => 1,
        'identity_territory_id' => $territoryId,
        'name' => 'Salvador',
        'state_code' => 'BA',
    ]);
    DB::table('forces')->insert(['code' => 'PM', 'name' => 'Policia Militar']);
    DB::table('sizes')->insert(['name' => '1B']);
}

/** @param list<ReportEntity>|null $reports
 * @return list<string>
 */
function reportRepositoryIds(?array $reports): array
{
    return array_map(fn (ReportEntity $report): string => $report->id()->value(), $reports ?? []);
}

test('persists complete snapshots and scalar projections with the author public UUID', function (): void {
    createReportRepositoryCatalog();
    $user = User::factory()->create();
    $report = ReportApplicationFixtures::completeReport(['createdBy' => $user->uuid]);
    $report->changeAttachments(new ReportAttachmentsValueObject(
        checklist: $report->attachments()?->checklist(),
        others: [new ReportAttachmentEntity(
            type: ReportAttachmentTypeEnum::OTHER,
            file: ReportApplicationFixtures::image(2)->file(),
            description: 'Additional survey',
            id: '550e8400-e29b-41d4-a716-446655440002',
        )],
    ));
    $repository = app(ReportRepositoryInterface::class);

    $inserted = $repository->insert($report);
    $restored = $repository->findById($report->id()->value());

    expect((new ReportDataMapperService)->map($inserted))->toEqual((new ReportDataMapperService)->map($report));
    expect($restored)->not->toBeNull();
    expect((new ReportDataMapperService)->map($restored))->toEqual((new ReportDataMapperService)->map($report));
    $this->assertDatabaseHas('reports', [
        'uuid' => $report->id()->value(),
        'created_by' => $user->id,
        'municipality_id' => 1,
        'force_id' => DB::table('forces')->where('code', 'PM')->value('id'),
        'size_id' => DB::table('sizes')->where('name', '1B')->value('id'),
        'typology' => 'Delegacia',
        'infrastructure_water_network' => 1,
        'infrastructure_high_voltage_network' => 0,
        'infrastructure_telephony' => 2,
    ]);
    $this->assertDatabaseHas('report_series', ['uuid' => $report->rootReportId()->value(), 'created_by' => $user->id]);
    $this->assertDatabaseHas('report_images', ['uuid' => ReportApplicationFixtures::image(1)->id()->value(), 'uploaded_by' => $user->id]);
    $this->assertDatabaseHas('report_attachments', ['description' => 'Additional survey', 'uploaded_by' => $user->id]);
});

test('persists absent draft sections and restores present empty sections after an update', function (): void {
    $user = User::factory()->create();
    $repository = app(ReportRepositoryInterface::class);
    $report = $repository->insert(new ReportEntity(createdBy: $user->uuid, createdAt: '2020-01-01 08:00:00'));
    $storedDraft = $repository->findById($report->id()->value());

    expect($storedDraft?->cover())->toBeNull();
    expect($storedDraft?->attachments())->toBeNull();
    $report->changeCover(new ReportCoverValueObject);
    $report->changePhotographicDocumentation(new ReportPhotographicDocumentationValueObject);
    $report->changeAttachments(new ReportAttachmentsValueObject);
    $updated = $repository->update($report);

    expect($updated->cover())->toBeInstanceOf(ReportCoverValueObject::class);
    expect($updated->cover()?->typology())->toBe('');
    expect($updated->attachments()?->checklist())->toBeNull();
    expect($updated->photographicDocumentation()?->images())->toBe([]);
    expect($updated->createdAt()->format('Y-m-d H:i:s'))->toBe('2020-01-01 08:00:00');
    expect((new ReportDataMapperService)->map($repository->findById($report->id()->value())))
        ->toEqual((new ReportDataMapperService)->map($updated));
    $this->assertDatabaseCount('reports', 1);
    $this->assertDatabaseHas('reports', ['uuid' => $report->id()->value(), 'status' => 'DRAFT']);
});

test('rejects a stale editable entity after another process generates its persisted version', function (): void {
    createReportRepositoryCatalog();
    $user = User::factory()->create();
    $repository = app(ReportRepositoryInterface::class);
    $report = $repository->insert(ReportApplicationFixtures::completeReport(['createdBy' => $user->uuid]));
    $staleReport = $repository->findById($report->id()->value());
    $report->registerGeneratedDocument(ReportApplicationFixtures::document($report->fileName()));
    $repository->update($report);
    $storedPayload = Report::query()->where('uuid', $report->id()->value())->value('payload');
    $staleReport->changeConclusion(new ReportConclusionValueObject('Changed after generation'));

    expect(fn () => $repository->update($staleReport))->toThrow(ReportAlreadyGeneratedException::class);

    expect(Report::query()->where('uuid', $report->id()->value())->value('payload'))->toBe($storedPayload);
    expect($repository->findById($report->id()->value())?->generatedDocument()?->storageIdentifier())->toBe('document:final');
    $this->assertDatabaseCount('reports', 1);
});

test('rejects a second generated result for an already generated persisted version', function (): void {
    createReportRepositoryCatalog();
    $user = User::factory()->create();
    $repository = app(ReportRepositoryInterface::class);
    $draft = $repository->insert(ReportApplicationFixtures::completeReport(['createdBy' => $user->uuid]));
    $anotherDraft = $repository->findById($draft->id()->value());
    $draft->registerGeneratedDocument(ReportApplicationFixtures::document($draft->fileName()));
    $repository->update($draft);
    $anotherDraft->registerGeneratedDocument(new GeneratedReportValueObject(
        storageIdentifier: 'document:replacement',
        fileName: $anotherDraft->fileName(),
        generatedAt: new DateTimeImmutable('2020-01-02 10:00:00'),
    ));

    expect(fn () => $repository->update($anotherDraft))->toThrow(ReportAlreadyGeneratedException::class);

    expect($repository->findById($draft->id()->value())?->generatedDocument()?->storageIdentifier())->toBe('document:final');
});

test('creates revisions in one family and rejects a competing revision without changing prior versions', function (): void {
    createReportRepositoryCatalog();
    $user = User::factory()->create();
    $repository = app(ReportRepositoryInterface::class);
    $root = ReportApplicationFixtures::completeReport([
        'createdBy' => $user->uuid,
        'generatedDocument' => ReportApplicationFixtures::document('SALVADOR_PM_1B_DELEGACIA.pdf'),
    ]);
    $repository->insert($root);

    $revision = $repository->insert($root->createRevision());
    expect(fn () => $repository->insert($root->createRevision()))->toThrow(QueryException::class);

    expect(reportRepositoryIds($repository->findByRootReportId($root->id()->value())))->toBe([$root->id()->value(), $revision->id()->value()]);
    expect($repository->findLatestByRootReportId($root->id()->value())?->id()->value())->toBe($revision->id()->value());
    expect(reportRepositoryIds($repository->findLatestReportById($root->id()->value())))->toBe([$revision->id()->value()]);
    expect($repository->findById($root->id()->value())?->generatedDocument()?->fileName())->toBe('SALVADOR_PM_1B_DELEGACIA.pdf');
    expect($revision->parentReportId()?->value())->toBe($root->id()->value());
    expect($revision->generatedDocument())->toBeNull();
    $this->assertDatabaseCount('report_series', 1);
    $this->assertDatabaseCount('reports', 2);
    $this->assertDatabaseCount('report_images', 2);
});

test('inherits institutional representatives into a revision and preserves the generated version when they are edited', function (): void {
    createReportRepositoryCatalog();
    $user = User::factory()->create();
    $repository = app(ReportRepositoryInterface::class);
    $root = $repository->insert(ReportApplicationFixtures::completeReport([
        'createdBy' => $user->uuid,
        'generalInformation' => new ReportGeneralInformationValueObject(
            inspectionDate: new DateTimeImmutable('2020-01-01'),
            collaborators: 'Equipe técnica',
            institutionalRepresentatives: [
                new ReportInstitutionalRepresentativeValueObject('Ana Souza', 'Secretária'),
                new ReportInstitutionalRepresentativeValueObject('José Santos', 'Diretor'),
            ],
        ),
        'generatedDocument' => ReportApplicationFixtures::document('SALVADOR_PM_1B_DELEGACIA.pdf'),
    ]));
    $originalPayload = Report::query()->where('uuid', $root->id()->value())->value('payload');
    $revision = $repository->insert($root->createRevision());
    expect($revision->generalInformation()?->institutionalRepresentatives())->toEqual($root->generalInformation()?->institutionalRepresentatives());
    $revision->changeGeneralInformation(new ReportGeneralInformationValueObject(
        inspectionDate: $revision->generalInformation()?->inspectionDate(),
        collaborators: 'Equipe técnica',
        institutionalRepresentatives: [new ReportInstitutionalRepresentativeValueObject('José Santos', 'Superintendente')],
    ));

    $repository->update($revision);
    $restoredRevision = $repository->findById($revision->id()->value());
    $restoredRoot = $repository->findById($root->id()->value());

    expect($restoredRevision?->generalInformation()?->institutionalRepresentatives())->toHaveCount(1);
    expect($restoredRevision?->generalInformation()?->institutionalRepresentatives()[0]->name())->toBe('José Santos');
    expect($restoredRevision?->generalInformation()?->institutionalRepresentatives()[0]->position())->toBe('Superintendente');
    expect($restoredRevision?->generalInformation()?->collaborators())->toBe('Equipe técnica');
    expect($restoredRoot?->generalInformation()?->institutionalRepresentatives())->toHaveCount(2);
    expect($restoredRoot?->generalInformation()?->institutionalRepresentatives()[0]->name())->toBe('Ana Souza');
    expect($restoredRoot?->generalInformation()?->institutionalRepresentatives()[1]->position())->toBe('Diretor');
    expect(Report::query()->where('uuid', $root->id()->value())->value('payload'))->toBe($originalPayload);
    $this->assertDatabaseHas('reports', ['uuid' => $root->id()->value(), 'status' => 'GENERATED']);
    $this->assertDatabaseHas('reports', ['uuid' => $revision->id()->value(), 'status' => 'DRAFT']);
    $this->assertDatabaseCount('reports', 2);
});

test('rejects revision persistence when its parent is still a draft', function (): void {
    $user = User::factory()->create();
    $repository = app(ReportRepositoryInterface::class);
    $root = $repository->insert(new ReportEntity(createdBy: $user->uuid));
    $revision = new ReportEntity(
        createdBy: $user->uuid,
        rootReportId: $root->id(),
        parentReportId: $root->id(),
        revisionNumber: 1,
    );

    expect(fn () => $repository->insert($revision))->toThrow(ReportNotGeneratedException::class);

    $this->assertDatabaseCount('reports', 1);
    $this->assertDatabaseCount('report_series', 1);
});

test('inherits removed upload history when a revision is reconstructed without that history', function (): void {
    createReportRepositoryCatalog();
    $user = User::factory()->create();
    $repository = app(ReportRepositoryInterface::class);
    $root = ReportApplicationFixtures::completeReport([
        'createdBy' => $user->uuid,
        'generatedDocument' => ReportApplicationFixtures::document('SALVADOR_PM_1B_DELEGACIA.pdf'),
        'uploadedImages' => [ReportApplicationFixtures::image(2)],
    ]);
    $repository->insert($root);
    $revision = ReportApplicationFixtures::completeReport([
        'createdBy' => $user->uuid,
        'id' => '550e8400-e29b-41d4-a716-446655440011',
        'rootReportId' => $root->id(),
        'parentReportId' => $root->id(),
        'revisionNumber' => 1,
    ]);

    $storedRevision = $repository->insert($revision);

    expect(array_map(fn (ReportImageEntity $image): int => $image->order(), $storedRevision->uploadedImages()))->toBe([2, 1]);
    $this->assertDatabaseCount('report_images', 4);
});

test('rejects a revision with a missing family without inserting records', function (): void {
    $user = User::factory()->create();
    $repository = app(ReportRepositoryInterface::class);
    $revision = new ReportEntity(
        createdBy: $user->uuid,
        rootReportId: '550e8400-e29b-41d4-a716-446655440010',
        parentReportId: '550e8400-e29b-41d4-a716-446655440010',
        revisionNumber: 1,
    );

    expect(fn () => $repository->insert($revision))->toThrow(InvalidReportRevisionException::class);

    $this->assertDatabaseEmpty('reports');
    $this->assertDatabaseEmpty('report_series');
});

test('preserves removed uploads and original captions while storing updated active captions', function (): void {
    createReportRepositoryCatalog();
    $user = User::factory()->create();
    $repository = app(ReportRepositoryInterface::class);
    $report = $repository->insert(ReportApplicationFixtures::completeReport([
        'createdBy' => $user->uuid,
        'photographicDocumentation' => new ReportPhotographicDocumentationValueObject([ReportApplicationFixtures::image(2)]),
    ]));
    $report->changePhotographicDocumentation(new ReportPhotographicDocumentationValueObject);
    $repository->update($report);
    $freshReport = ReportApplicationFixtures::completeReport(['createdBy' => $user->uuid]);

    $restored = $repository->update($freshReport);
    $restored->changePhotographicDocumentation(new ReportPhotographicDocumentationValueObject([
        ReportApplicationFixtures::image(2)->withCaption('Updated caption'),
    ]));
    $updated = $repository->update($restored);

    expect(array_map(fn (ReportImageEntity $image): int => $image->order(), $updated->uploadedImages()))->toBe([1, 2]);
    expect($updated->uploadedImages()[1]->caption())->toBe('Figura do terreno 2');
    expect($updated->photographicDocumentation()?->images()[0]->caption())->toBe('Updated caption');
    $this->assertDatabaseCount('report_images', 2);
    $this->assertDatabaseHas('report_images', ['uuid' => ReportApplicationFixtures::image(2)->id()->value(), 'position' => 2, 'caption' => 'Updated caption']);
});

test('rejects reordering a persisted upload through a newly constructed entity without history', function (): void {
    createReportRepositoryCatalog();
    $user = User::factory()->create();
    $repository = app(ReportRepositoryInterface::class);
    $report = $repository->insert(ReportApplicationFixtures::completeReport([
        'createdBy' => $user->uuid,
        'photographicDocumentation' => new ReportPhotographicDocumentationValueObject([ReportApplicationFixtures::image(2)]),
    ]));
    $originalImage = ReportApplicationFixtures::image(2);
    $freshReport = ReportApplicationFixtures::completeReport([
        'createdBy' => $user->uuid,
        'photographicDocumentation' => new ReportPhotographicDocumentationValueObject([new ReportImageEntity(
            file: $originalImage->file(),
            order: 9,
            id: $originalImage->id(),
        )]),
    ]);

    expect(fn () => $repository->update($freshReport))->toThrow(InvalidReportImageOrderException::class);

    expect($repository->findById($report->id()->value())?->photographicDocumentation()?->images()[0]->order())->toBe(2);
    $this->assertDatabaseHas('report_images', ['uuid' => $originalImage->id()->value(), 'position' => 2]);
});

test('replaces an attachment file with the same identity while preserving only its current reference', function (): void {
    $user = User::factory()->create();
    $repository = app(ReportRepositoryInterface::class);
    $attachmentId = '550e8400-e29b-41d4-a716-446655440002';
    $report = $repository->insert(new ReportEntity(
        createdBy: $user->uuid,
        attachments: new ReportAttachmentsValueObject(others: [new ReportAttachmentEntity(
            type: ReportAttachmentTypeEnum::OTHER,
            file: ReportApplicationFixtures::image(2)->file(),
            description: 'Original survey',
            id: $attachmentId,
        )]),
    ));
    $replacementFile = ReportApplicationFixtures::image(3)->file();
    $report->changeAttachments(new ReportAttachmentsValueObject(others: [new ReportAttachmentEntity(
        type: ReportAttachmentTypeEnum::OTHER,
        file: $replacementFile,
        description: 'Updated survey',
        id: $attachmentId,
    )]));

    $updated = $repository->update($report);

    expect($updated->attachments()?->others()[0]->id()->value())->toBe($attachmentId);
    expect($updated->attachments()?->others()[0]->file()->checksum())->toBe($replacementFile->checksum());
    $this->assertDatabaseCount('report_attachments', 1);
    $this->assertDatabaseHas('report_attachments', ['uuid' => $attachmentId, 'file_hash' => $replacementFile->checksum(), 'description' => 'Updated survey']);
    $this->assertDatabaseMissing('report_attachments', ['file_hash' => ReportApplicationFixtures::image(2)->file()->checksum()]);
});

test('preserves inherited media authors and upload dates while attributing new revision media to its author', function (): void {
    createReportRepositoryCatalog();
    $originalAuthor = User::factory()->create();
    $revisionAuthor = User::factory()->create();
    $repository = app(ReportRepositoryInterface::class);
    $inheritedAttachment = new ReportAttachmentEntity(
        type: ReportAttachmentTypeEnum::OTHER,
        file: ReportApplicationFixtures::image(2)->file(),
        description: 'Original survey',
        id: '550e8400-e29b-41d4-a716-446655440002',
    );
    $checklist = ReportApplicationFixtures::completeReport()->attachments()?->checklist();
    $root = ReportApplicationFixtures::completeReport([
        'createdBy' => $originalAuthor->uuid,
        'attachments' => new ReportAttachmentsValueObject(checklist: $checklist, others: [$inheritedAttachment]),
        'generatedDocument' => ReportApplicationFixtures::document('SALVADOR_PM_1B_DELEGACIA.pdf'),
    ]);
    $repository->insert($root);
    $revision = $root->createRevision($revisionAuthor->uuid);
    $revision->changePhotographicDocumentation(new ReportPhotographicDocumentationValueObject([ReportApplicationFixtures::image(3)]));
    $newAttachment = new ReportAttachmentEntity(
        type: ReportAttachmentTypeEnum::OTHER,
        file: ReportApplicationFixtures::image(4)->file(),
        description: 'Revision survey',
        id: '550e8400-e29b-41d4-a716-446655440004',
    );
    $revision->changeAttachments(new ReportAttachmentsValueObject(checklist: $checklist, others: [$inheritedAttachment, $newAttachment]));

    $storedRevision = $repository->insert($revision);
    $revisionModel = Report::query()->where('uuid', $storedRevision->id()->value())->firstOrFail();

    $this->assertDatabaseHas('report_images', [
        'report_id' => $revisionModel->id,
        'uuid' => ReportApplicationFixtures::image(1)->id()->value(),
        'uploaded_by' => $originalAuthor->id,
        'created_at' => '2020-01-01 08:00:00',
    ]);
    $this->assertDatabaseHas('report_attachments', [
        'report_id' => $revisionModel->id,
        'uuid' => $inheritedAttachment->id()->value(),
        'uploaded_by' => $originalAuthor->id,
        'created_at' => '2020-01-01 08:00:00',
    ]);
    $this->assertDatabaseHas('report_images', [
        'report_id' => $revisionModel->id,
        'uuid' => ReportApplicationFixtures::image(3)->id()->value(),
        'uploaded_by' => $revisionAuthor->id,
        'created_at' => $revision->updatedAt()->format('Y-m-d H:i:s'),
    ]);
    $this->assertDatabaseHas('report_attachments', [
        'report_id' => $revisionModel->id,
        'uuid' => $newAttachment->id()->value(),
        'uploaded_by' => $revisionAuthor->id,
        'created_at' => $revision->updatedAt()->format('Y-m-d H:i:s'),
    ]);
});

test('rejects changes to persisted report identity metadata without changing its author', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $repository = app(ReportRepositoryInterface::class);
    $report = $repository->insert(new ReportEntity(createdBy: $user->uuid, createdAt: '2020-01-01 08:00:00'));
    $forgedReport = new ReportEntity(id: $report->id(), createdBy: $otherUser->uuid, createdAt: $report->createdAt());

    expect(fn () => $repository->update($forgedReport))->toThrow(InvalidReportRevisionException::class);

    $this->assertDatabaseHas('reports', ['uuid' => $report->id()->value(), 'created_by' => $user->id]);
    $this->assertDatabaseCount('reports', 1);
});

test('rolls back a new family and report when the municipality foreign reference is missing', function (): void {
    $user = User::factory()->create();
    $repository = app(ReportRepositoryInterface::class);
    $report = ReportApplicationFixtures::completeReport(['createdBy' => $user->uuid]);

    expect(fn () => $repository->insert($report))->toThrow(QueryException::class);

    $this->assertDatabaseEmpty('report_series');
    $this->assertDatabaseEmpty('reports');
    $this->assertDatabaseEmpty('report_images');
    $this->assertDatabaseEmpty('report_attachments');
});

test('rejects a missing author without persisting a family', function (): void {
    $repository = app(ReportRepositoryInterface::class);
    $report = new ReportEntity(createdBy: '550e8400-e29b-41d4-a716-446655440000');

    expect(fn () => $repository->insert($report))->toThrow(ModelNotFoundException::class);

    $this->assertDatabaseEmpty('report_series');
    $this->assertDatabaseEmpty('reports');
});

test('returns empty query results for missing identifiers and rejects updates to missing reports', function (): void {
    $user = User::factory()->create();
    $repository = app(ReportRepositoryInterface::class);
    $missingReport = new ReportEntity(createdBy: $user->uuid);
    $missingId = $missingReport->id()->value();

    expect($repository->findById($missingId))->toBeNull();
    expect($repository->findByRootReportId($missingId))->toBe([]);
    expect($repository->findLatestByRootReportId($missingId))->toBeNull();
    expect($repository->findLatestReportById($missingId))->toBe([]);
    expect($repository->getReportByUserId($missingId))->toBe([]);
    expect($repository->getReportByCoordinateId('999'))->toBe([]);
    expect($repository->findReportByMunicipalityId('999'))->toBe([]);
    expect(fn () => $repository->update($missingReport))->toThrow(ReportNotFoundException::class);

    $this->assertDatabaseEmpty('reports');
});

test('filters combined user and coordination constraints and orders dashboard reports consistently', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $coordinationId = DB::table('coordinations')->insertGetId(['code' => 'CEIRF', 'name' => 'CEIRF']);
    $otherCoordinationId = DB::table('coordinations')->insertGetId(['code' => 'OTHER', 'name' => 'Other']);
    $reportTypeId = DB::table('report_types')->insertGetId(['coordination_id' => $coordinationId, 'code' => 'inspection', 'name' => 'Inspection']);
    $otherReportTypeId = DB::table('report_types')->insertGetId(['coordination_id' => $otherCoordinationId, 'code' => 'inspection', 'name' => 'Inspection']);
    $first = Report::factory()->create(['created_by' => $user->id, 'report_type_id' => $reportTypeId, 'created_at' => '2020-01-01 08:00:00']);
    $otherAuthor = Report::factory()->create(['created_by' => $otherUser->id, 'report_type_id' => $reportTypeId, 'created_at' => '2020-01-01 08:00:00']);
    $otherCoordination = Report::factory()->create(['created_by' => $user->id, 'report_type_id' => $otherReportTypeId, 'created_at' => '2020-01-01 08:00:00']);
    $unclassified = Report::factory()->create(['created_by' => $user->id, 'created_at' => '2020-01-01 08:00:00']);
    $repository = app(ReportRepositoryInterface::class);

    expect(reportRepositoryIds($repository->getReportByUserIdAndCoordinateId($user->uuid, (string) $coordinationId)))->toBe([$first->uuid]);
    expect(reportRepositoryIds($repository->getReportByUserId($user->uuid)))->toBe([$unclassified->uuid, $otherCoordination->uuid, $first->uuid]);
    expect(reportRepositoryIds($repository->getReportByCoordinateId((string) $coordinationId)))->toBe([$otherAuthor->uuid, $first->uuid]);
    expect(reportRepositoryIds($repository->getDashboardReports()))->toBe([$unclassified->uuid, $otherCoordination->uuid, $otherAuthor->uuid, $first->uuid]);
});

test('filters reports by municipality using their persisted catalog reference', function (): void {
    createReportRepositoryCatalog();
    $user = User::factory()->create();
    $repository = app(ReportRepositoryInterface::class);
    $report = $repository->insert(ReportApplicationFixtures::completeReport(['createdBy' => $user->uuid]));
    $repository->insert(new ReportEntity(createdBy: $user->uuid));

    expect(reportRepositoryIds($repository->findReportByMunicipalityId('1')))->toBe([$report->id()->value()]);
});

test('counts reports inside calendar month boundaries including leap day and excludes adjacent months', function (): void {
    $user = User::factory()->create();
    foreach (['2020-01-31 23:59:59', '2020-02-01 00:00:00', '2020-02-29 23:59:59', '2020-03-01 00:00:00'] as $createdAt) {
        Report::factory()->create(['created_by' => $user->id, 'created_at' => $createdAt, 'updated_at' => $createdAt]);
    }
    $repository = app(ReportRepositoryInterface::class);

    expect($repository->countAllReports())->toBe(4);
    expect($repository->countReportsByMonth('2020-02'))->toBe([['month' => '2020-02', 'count' => 2]]);
    expect($repository->countReportsByMonth('2020-04'))->toBe([]);
});

test('rejects invalid calendar month filters', function (string $month): void {
    $repository = app(ReportRepositoryInterface::class);

    expect(fn () => $repository->countReportsByMonth($month))->toThrow(InvalidArgumentException::class);
})->with(['invalid month' => ['2020-13'], 'non padded month' => ['2020-2'], 'empty month' => ['']]);
