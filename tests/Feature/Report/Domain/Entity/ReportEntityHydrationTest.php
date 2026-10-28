<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use src\Modules\Identity\Model\User;
use src\Modules\Report\Application\Interfaces\Mapper\LegacyReportQueryMapperInterface;
use src\Modules\Report\Application\Interfaces\Mapper\ReportPersistenceMapperInterface;
use src\Modules\Report\Domain\Entity\ReportEntity;
use src\Modules\Report\Domain\Enum\ChecklistAnswerEnum;
use src\Modules\Report\Domain\Enum\ForceEnum;
use src\Modules\Report\Domain\Enum\ReportSizeEnum;
use src\Modules\Report\Domain\Enum\ReportStatusEnum;
use src\Modules\Report\Domain\Repository\ReportRepositoryInterface;
use src\Modules\Report\Domain\ValueObject\ReportConclusionValueObject;
use src\Modules\Report\Model\Report;
use Tests\Fixtures\ReportApplicationFixtures;

test('restores generated snapshots and upload history without invoking legacy hydration', function (): void {
    $original = ReportApplicationFixtures::completeReport([
        'uploadedImages' => [ReportApplicationFixtures::image(1), ReportApplicationFixtures::image(2)],
    ]);
    $original->registerGeneratedDocument(ReportApplicationFixtures::document($original->fileName()));
    $mapper = app(ReportPersistenceMapperInterface::class);
    $legacyMapper = Mockery::mock(LegacyReportQueryMapperInterface::class);
    $legacyMapper->shouldNotReceive('fromModel');
    $model = new Report([
        'uuid' => $original->id()->value(),
        'status' => ReportStatusEnum::GENERATED,
        'payload' => $mapper->toPayload($original),
    ]);

    $restored = ReportEntity::fromModel($model, $mapper, $legacyMapper);

    expect($restored->isGenerated())->toBeTrue();
    expect($restored->status())->toBe(ReportStatusEnum::GENERATED);
    expect($restored->id()->value())->toBe($original->id()->value());
    expect($restored->generatedDocument()?->storageIdentifier())->toBe('document:final');
    expect($restored->generatedDocument()?->fileName())->toBe($original->fileName());
    expect($restored->generatedDocument()?->generatedAt()->format('Y-m-d H:i:s'))->toBe('2020-01-01 10:00:00');
    expect($restored->uploadedImages())->toHaveCount(2);
    expect($restored->uploadedImages()[1]->id()->value())->toBe('550e8400-e29b-41d4-a716-000000000002');
    expect($restored->uploadedImages()[1]->order())->toBe(2);
    expect($restored->photographicDocumentation()?->images())->toBe([]);
});

test('restores legacy scalar drafts and their revision family without blocking dashboard reads', function (): void {
    $user = User::factory()->create();
    $territoryId = DB::table('identity_territories')->insertGetId(['name' => 'Salvador']);
    $municipalityId = DB::table('municipalities')->insertGetId([
        'identity_territory_id' => $territoryId,
        'name' => 'Salvador',
        'state_code' => 'BA',
    ]);
    $forceId = DB::table('forces')->insertGetId(['code' => 'BM', 'name' => 'Bombeiro militar']);
    $sizeId = DB::table('sizes')->insertGetId(['name' => 'Sem Padrão']);
    $root = Report::factory()->create([
        'payload' => null,
        'created_by' => $user->id,
        'municipality_id' => $municipalityId,
        'force_id' => $forceId,
        'size_id' => $sizeId,
        'typology' => 'Unidade',
        'sei_number' => 'SEI 100/2020',
        'inspection_date' => '2020-01-01',
        'present_collaborators' => 'Equipe de vistoria',
        'infrastructure_water_network' => 1,
        'infrastructure_high_voltage_network' => 0,
        'infrastructure_telephony' => 2,
        'checklist_sei_construction_request' => 0,
        'checklist_compatible_dimensions' => 1,
        'checklist_demolition_required' => 2,
        'conclusion' => 'Viabilidade condicionada.',
        'created_at' => '2020-01-01 08:00:00',
        'updated_at' => '2020-01-01 09:00:00',
    ]);
    $revision = Report::factory()->create([
        'payload' => null,
        'created_by' => $user->id,
        'report_series_id' => $root->report_series_id,
        'previous_report_id' => $root->id,
        'revision_number' => 1,
        'created_at' => '2020-01-02 08:00:00',
        'updated_at' => null,
    ]);
    $repository = app(ReportRepositoryInterface::class);

    $history = $repository->findByRootReportId($root->uuid);
    $dashboard = $repository->getDashboardReports();

    expect(array_map(fn (ReportEntity $report): string => $report->id()->value(), $history))->toBe([$root->uuid, $revision->uuid]);
    expect(array_map(fn (ReportEntity $report): string => $report->id()->value(), $dashboard ?? []))->toBe([$revision->uuid, $root->uuid]);
    expect($history[0]->createdBy()->value())->toBe($user->uuid);
    expect($history[0]->cover()?->municipality()?->id())->toBe($municipalityId);
    expect($history[0]->cover()?->force())->toBe(ForceEnum::CBM);
    expect($history[0]->cover()?->size())->toBe(ReportSizeEnum::WITHOUT_STANDARD);
    expect($history[0]->cover()?->typology())->toBe('Unidade');
    expect($history[0]->cover()?->seiNumber()?->value())->toBe('SEI 100/2020');
    expect($history[0]->generalInformation()?->inspectionDate()?->format('Y-m-d'))->toBe('2020-01-01');
    expect($history[0]->generalInformation()?->collaborators())->toBe('Equipe de vistoria');
    expect($history[0]->generalInformation()?->institutionalRepresentatives())->toBe([]);
    expect($history[0]->infrastructure()?->waterNetwork())->toBe(ChecklistAnswerEnum::YES);
    expect($history[0]->infrastructure()?->highVoltageNetwork())->toBe(ChecklistAnswerEnum::NO);
    expect($history[0]->infrastructure()?->telephony())->toBe(ChecklistAnswerEnum::NOT_APPLICABLE);
    expect($history[0]->attachments()?->checklist()?->seiConstructionRequest())->toBe(ChecklistAnswerEnum::NO);
    expect($history[0]->attachments()?->checklist()?->compatibleDimensions())->toBe(ChecklistAnswerEnum::YES);
    expect($history[0]->attachments()?->checklist()?->demolitionRequired())->toBe(ChecklistAnswerEnum::NOT_APPLICABLE);
    expect($history[0]->conclusion()?->content())->toBe('Viabilidade condicionada.');
    expect($history[1]->parentReportId()?->value())->toBe($root->uuid);
    expect($history[1]->rootReportId()->value())->toBe($root->uuid);
    expect($history[1]->cover())->toBeNull();
    expect($history[1]->attachments())->toBeNull();
    $this->assertDatabaseHas('reports', ['id' => $root->id, 'payload' => null, 'updated_at' => '2020-01-01 09:00:00']);
    $this->assertDatabaseHas('reports', ['id' => $revision->id, 'payload' => null, 'updated_at' => null]);
});

test('restores known legacy media while preserving unknown images only in upload history', function (): void {
    $user = User::factory()->create();
    $report = Report::factory()->create(['created_by' => $user->id, 'payload' => null]);
    $imageAttributes = [
        'report_id' => $report->id,
        'uploaded_by' => $user->id,
        'original_name' => 'map.png',
        'storage_path' => 'legacy/map.png',
        'mime_type' => 'image/png',
        'size_bytes' => 100,
        'created_at' => '2020-01-01 08:00:00',
    ];
    DB::table('report_images')->insert([
        [...$imageAttributes, 'uuid' => '550e8400-e29b-41d4-a716-446655440001', 'file_hash' => str_repeat('1', 64), 'type' => 'location_map', 'position' => null, 'caption' => 'Localização'],
        [...$imageAttributes, 'uuid' => '550e8400-e29b-41d4-a716-446655440002', 'file_hash' => str_repeat('2', 64), 'type' => 'photographic_documentation', 'position' => 7, 'caption' => 'Segunda fotografia'],
        [...$imageAttributes, 'uuid' => '550e8400-e29b-41d4-a716-446655440003', 'file_hash' => str_repeat('3', 64), 'type' => 'photographic_documentation', 'position' => 3, 'caption' => 'Primeira fotografia'],
        [...$imageAttributes, 'uuid' => '550e8400-e29b-41d4-a716-446655440004', 'file_hash' => str_repeat('4', 64), 'type' => 'unknown_legacy_role', 'position' => null, 'caption' => 'Arquivo histórico'],
    ]);
    DB::table('report_attachments')->insert([
        'report_id' => $report->id,
        'uploaded_by' => $user->id,
        'uuid' => '550e8400-e29b-41d4-a716-446655440005',
        'type' => 'OTHER',
        'original_name' => 'survey.pdf',
        'storage_path' => 'legacy/survey.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 200,
        'file_hash' => str_repeat('5', 64),
        'description' => 'Levantamento complementar',
        'created_at' => '2020-01-01 08:00:00',
    ]);

    $restored = ReportEntity::fromModel(
        $report,
        app(ReportPersistenceMapperInterface::class),
        app(LegacyReportQueryMapperInterface::class),
    );

    expect($restored->location()?->locationMap()?->id()->value())->toBe('550e8400-e29b-41d4-a716-446655440001');
    expect($restored->location()?->locationMap()?->order())->toBe(8);
    expect($restored->location()?->locationMap()?->file()->storageIdentifier())->toBe('legacy/map.png');
    expect($restored->location()?->locationMap()?->file()->checksum())->toBe(str_repeat('1', 64));
    expect($restored->photographicDocumentation()?->images()[0]->order())->toBe(3);
    expect($restored->photographicDocumentation()?->images()[1]->order())->toBe(7);
    expect($restored->photographicDocumentation()?->images()[0]->caption())->toBe('Primeira fotografia');
    expect($restored->uploadedImages())->toHaveCount(4);
    expect($restored->uploadedImages()[3]->id()->value())->toBe('550e8400-e29b-41d4-a716-446655440004');
    expect($restored->uploadedImages()[3]->order())->toBe(9);
    expect($restored->attachments()?->others()[0]->id()->value())->toBe('550e8400-e29b-41d4-a716-446655440005');
    expect($restored->attachments()?->others()[0]->description())->toBe('Levantamento complementar');
    expect($restored->attachments()?->others()[0]->file()->fileName())->toBe('survey.pdf');
    $this->assertDatabaseHas('report_images', ['uuid' => '550e8400-e29b-41d4-a716-446655440001', 'position' => null]);
    $this->assertDatabaseHas('reports', ['id' => $report->id, 'payload' => null]);
});

test('creates a snapshot when editing a legacy draft without overwriting unrelated legacy fields', function (): void {
    $model = Report::factory()->create([
        'payload' => null,
        'typology' => 'Unidade original',
        'objective' => 'Objetivo preservado do cadastro anterior.',
    ]);
    $repository = app(ReportRepositoryInterface::class);
    $report = $repository->findById($model->uuid);
    expect($report)->not->toBeNull();
    $report->changeConclusion(new ReportConclusionValueObject('Nova conclusão.'));

    $updated = $repository->update($report);

    expect($updated->cover()?->typology())->toBe('Unidade original');
    expect($updated->conclusion()?->content())->toBe('Nova conclusão.');
    expect($model->refresh()->payload)->not->toBeNull();
    expect($repository->findById($model->uuid)?->conclusion()?->content())->toBe('Nova conclusão.');
    $this->assertDatabaseHas('reports', ['id' => $model->id, 'objective' => 'Objetivo preservado do cadastro anterior.']);
});

test('rejects a generated legacy report without its required document snapshot', function (): void {
    $report = Report::factory()->create(['payload' => null, 'status' => 'GENERATED']);

    expect(fn (): ReportEntity => ReportEntity::fromModel(
        $report,
        app(ReportPersistenceMapperInterface::class),
        app(LegacyReportQueryMapperInterface::class),
    ))
        ->toThrow(UnexpectedValueException::class, 'O relatório gerado não possui um snapshot de conteúdo.');

    $this->assertDatabaseHas('reports', ['id' => $report->id, 'payload' => null, 'status' => 'GENERATED']);
});

test('rejects an unknown legacy attachment type instead of discarding its file', function (): void {
    $user = User::factory()->create();
    $report = Report::factory()->create(['created_by' => $user->id, 'payload' => null]);
    DB::table('report_attachments')->insert([
        'report_id' => $report->id,
        'uploaded_by' => $user->id,
        'uuid' => '550e8400-e29b-41d4-a716-446655440006',
        'type' => 'UNKNOWN',
        'original_name' => 'legacy.pdf',
        'storage_path' => 'legacy/document.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 200,
        'file_hash' => str_repeat('6', 64),
        'created_at' => '2020-01-01 08:00:00',
    ]);

    expect(fn (): ReportEntity => ReportEntity::fromModel(
        $report,
        app(ReportPersistenceMapperInterface::class),
        app(LegacyReportQueryMapperInterface::class),
    ))
        ->toThrow(UnexpectedValueException::class, 'O relatório legado possui um tipo de anexo desconhecido.');

    $this->assertDatabaseHas('report_attachments', ['uuid' => '550e8400-e29b-41d4-a716-446655440006', 'type' => 'UNKNOWN']);
    $this->assertDatabaseHas('reports', ['id' => $report->id, 'payload' => null]);
});
