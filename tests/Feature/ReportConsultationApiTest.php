<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use src\Modules\Identity\Domain\Enum\UserRoleEnum;
use src\Modules\Identity\Model\User;
use src\Modules\Report\Domain\Entity\ReportAttachmentEntity;
use src\Modules\Report\Domain\Entity\ReportEntity;
use src\Modules\Report\Domain\Enum\ForceEnum;
use src\Modules\Report\Domain\Enum\ReportAttachmentTypeEnum;
use src\Modules\Report\Domain\Enum\ReportSizeEnum;
use src\Modules\Report\Domain\Repository\ReportRepositoryInterface;
use src\Modules\Report\Domain\ValueObject\GeneratedReportValueObject;
use src\Modules\Report\Domain\ValueObject\MunicipalityValueObject;
use src\Modules\Report\Domain\ValueObject\ReportAttachmentsValueObject;
use src\Modules\Report\Domain\ValueObject\ReportCoverValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPhotographicDocumentationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPreImplementationValueObject;
use src\Modules\Report\Domain\ValueObject\SeiNumberValueObject;
use src\Modules\Report\Model\Report;
use Tests\Fixtures\ReportApplicationFixtures;

function reportConsultationAccessToken(User $user): string
{
    $response = test()->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password']);
    $response->assertOk();
    Auth::forgetGuards();

    return $response->json('data.access_token');
}

/** @return array{coordination_id: int, report_type_id: int} */
function reportConsultationCoordination(string $code): array
{
    $coordinationId = DB::table('coordinations')->insertGetId(['code' => $code, 'name' => $code]);
    $reportTypeId = DB::table('report_types')->insertGetId([
        'coordination_id' => $coordinationId, 'code' => 'inspection', 'name' => 'Vistorias de Terreno',
    ]);

    return ['coordination_id' => $coordinationId, 'report_type_id' => $reportTypeId];
}

/** @return array{salvador: MunicipalityValueObject, conquista: MunicipalityValueObject} */
function reportConsultationCatalog(): array
{
    $territoryId = DB::table('identity_territories')->insertGetId(['name' => 'Território de teste']);
    $salvadorId = DB::table('municipalities')->insertGetId([
        'identity_territory_id' => $territoryId, 'name' => 'Salvador', 'state_code' => 'BA',
    ]);
    $conquistaId = DB::table('municipalities')->insertGetId([
        'identity_territory_id' => $territoryId, 'name' => 'Vitória da Conquista', 'state_code' => 'BA',
    ]);
    DB::table('forces')->insert(['code' => 'PM', 'name' => 'Polícia Militar']);
    DB::table('sizes')->insert(['name' => '1B']);

    return [
        'salvador' => new MunicipalityValueObject($salvadorId, 'Salvador'),
        'conquista' => new MunicipalityValueObject($conquistaId, 'Vitória da Conquista'),
    ];
}

function reportConsultationRecord(
    User $author,
    MunicipalityValueObject $municipality,
    ?int $reportTypeId = null,
    string $typology = 'DEAM 1B',
    bool $generated = true,
    string $createdAt = '2020-01-01 08:00:00',
    ?string $storageIdentifier = null,
    bool $withPrivateMedia = false,
): ReportEntity {
    $report = ReportApplicationFixtures::completeReport([
        'id' => (string) Str::uuid(), 'createdBy' => $author->uuid, 'createdAt' => $createdAt, 'updatedAt' => $createdAt,
        'coordinationId' => $reportTypeId === null ? $author->coordination_id : (int) DB::table('report_types')->where('id', $reportTypeId)->value('coordination_id'),
        'reportTypeId' => $reportTypeId,
        'cover' => new ReportCoverValueObject(
            municipality: $municipality, force: ForceEnum::PM, size: ReportSizeEnum::ONE_B,
            typology: $typology, seiNumber: new SeiNumberValueObject('012.3456.2020.0000012-34'),
        ),
    ]);
    if ($withPrivateMedia) {
        $report->changePhotographicDocumentation(new ReportPhotographicDocumentationValueObject([
            ReportApplicationFixtures::image(2), ReportApplicationFixtures::image(3),
        ]));
        $report->changePhotographicDocumentation(new ReportPhotographicDocumentationValueObject([ReportApplicationFixtures::image(3)]));
        $report->changePreImplementation(new ReportPreImplementationValueObject(ReportApplicationFixtures::image(4)));
        $report->changeAttachments(new ReportAttachmentsValueObject(
            checklist: $report->attachments()->checklist(),
            others: [new ReportAttachmentEntity(
                type: ReportAttachmentTypeEnum::OTHER, file: ReportApplicationFixtures::image(5)->file(),
                description: 'Arquivo complementar para edição', id: '550e8400-e29b-41d4-a716-446655440005',
            )],
        ));
    }
    if ($generated) {
        $report->registerGeneratedDocument(new GeneratedReportValueObject(
            storageIdentifier: $storageIdentifier ?? 'reports/'.$report->id()->value().'/documents/'.Str::uuid().'.pdf',
            fileName: $report->fileName(), generatedAt: new DateTimeImmutable('2020-01-01 10:00:00'),
        ));
    }

    return app(ReportRepositoryInterface::class)->insert($report);
}

function reportConsultationReview(ReportEntity $report, User $reviewer, string $status): void
{
    DB::table('report_reviews')->insert([
        'report_id' => Report::query()->where('uuid', $report->id()->value())->value('id'),
        'reviewer_id' => $reviewer->id, 'status' => $status, 'created_at' => '2020-01-01 09:00:00',
        'reviewed_at' => strtoupper(trim($status)) === 'APPROVED' ? '2020-01-01 10:00:00' : null,
    ]);
}

dataset('report consultation roles', [
    'superuser' => [UserRoleEnum::SUPERUSER], 'reviewer' => [UserRoleEnum::REVIEWER],
    'operator' => [UserRoleEnum::OPERATOR], 'viewer' => [UserRoleEnum::VIEWER],
]);

describe('report metadata authorization', function (): void {
    test('public read responses omit removed uploads and every private media storage token', function (string $path, string $dataPath): void {
        $municipalities = reportConsultationCatalog();
        $coordination = reportConsultationCoordination('COTEC');
        $reader = User::factory()->withRole(UserRoleEnum::VIEWER)->create();
        $author = User::factory()->create(['coordination_id' => $coordination['coordination_id']]);
        $report = reportConsultationRecord($author, $municipalities['salvador'], $coordination['report_type_id'], withPrivateMedia: true);
        $token = reportConsultationAccessToken($reader);

        $response = $this->withToken($token)->getJson(str_replace('{id}', $report->id()->value(), $path));

        $response->assertOk()->assertJsonPath($dataPath.'.id', $report->id()->value())
            ->assertJsonPath($dataPath.'.cover.typology', 'DEAM 1B')->assertJsonMissingPath($dataPath.'.uploaded_images');
        expect(json_encode($response->json($dataPath), JSON_THROW_ON_ERROR))
            ->not->toContain('storage_identifier')->not->toContain('asset:')
            ->not->toContain(ReportApplicationFixtures::image(2)->id()->value());
    })->with([
        'metadata' => ['/api/reports/{id}', 'data'],
        'latest' => ['/api/reports/{id}/latest', 'data'],
        'history' => ['/api/reports/{id}/history', 'data.reports.0'],
        'legacy list' => ['/api/reports', 'data.reports.0'],
        'dashboard' => ['/api/reports/dashboard', 'data.reports.0'],
    ]);

    test('reviewers and operators outside the editorial scope cannot retrieve upload history or storage tokens', function (UserRoleEnum $role): void {
        $municipalities = reportConsultationCatalog();
        $own = reportConsultationCoordination('COTEC');
        $other = reportConsultationCoordination('OTHER');
        $reader = User::factory()->withRole($role)->create(['coordination_id' => $own['coordination_id']]);
        $author = User::factory()->create(['coordination_id' => $other['coordination_id']]);
        $report = reportConsultationRecord($author, $municipalities['salvador'], $other['report_type_id'], withPrivateMedia: true);
        $token = reportConsultationAccessToken($reader);

        $response = $this->withToken($token)->getJson('/api/reports/'.$report->id()->value());

        $response->assertOk()->assertJsonMissingPath('data.uploaded_images');
        expect(json_encode($response->json('data'), JSON_THROW_ON_ERROR))
            ->not->toContain('storage_identifier')->not->toContain('asset:')
            ->not->toContain(ReportApplicationFixtures::image(2)->id()->value());
    })->with(['reviewer from another coordination' => [UserRoleEnum::REVIEWER], 'operator from another coordination' => [UserRoleEnum::OPERATOR]]);

    test('authorized editorial readers retain historical upload identities and the tokens needed for report editing', function (UserRoleEnum $role, bool $isAuthor): void {
        $municipalities = reportConsultationCatalog();
        $coordination = reportConsultationCoordination('COTEC');
        $reader = User::factory()->withRole($role)->create(['coordination_id' => $coordination['coordination_id']]);
        $author = $isAuthor ? $reader : User::factory()->create(['coordination_id' => $coordination['coordination_id']]);
        $report = reportConsultationRecord($author, $municipalities['salvador'], $coordination['report_type_id'], withPrivateMedia: true);
        $token = reportConsultationAccessToken($reader);

        $response = $this->withToken($token)->getJson('/api/reports/'.$report->id()->value());

        $response->assertOk()->assertJsonPath('data.location.municipality_in_state_map.file.storage_identifier', 'asset:1')
            ->assertJsonPath('data.photographic_documentation.images.0.file.storage_identifier', 'asset:3')
            ->assertJsonPath('data.pre_implementation.image.file.storage_identifier', 'asset:4')
            ->assertJsonPath('data.attachments.others.0.file.storage_identifier', 'asset:5')
            ->assertJsonMissingPath('data.generated_document.storage_identifier');
        expect(array_column($response->json('data.uploaded_images'), 'id'))->toContain(ReportApplicationFixtures::image(2)->id()->value());
        $response->assertJsonCount(1, 'data.photographic_documentation.images');
    })->with([
        'superuser' => [UserRoleEnum::SUPERUSER, false], 'reviewer in origin coordination' => [UserRoleEnum::REVIEWER, false],
        'operator author' => [UserRoleEnum::OPERATOR, true],
    ]);

    test('each profile consults real metadata across coordinations without exposing the storage key', function (UserRoleEnum $role): void {
        $municipalities = reportConsultationCatalog();
        $own = reportConsultationCoordination('COTEC');
        $other = reportConsultationCoordination('OTHER');
        $reader = User::factory()->withRole($role)->create(['coordination_id' => $own['coordination_id']]);
        $author = User::factory()->create(['coordination_id' => $other['coordination_id']]);
        $report = reportConsultationRecord($author, $municipalities['conquista'], $other['report_type_id']);
        $token = reportConsultationAccessToken($reader);

        $response = $this->withToken($token)->getJson('/api/reports/'.$report->id()->value());

        $response->assertOk()->assertJsonPath('data.id', $report->id()->value())->assertJsonPath('data.status', 'GENERATED')
            ->assertJsonPath('data.cover.municipality.name', 'Vitória da Conquista')->assertJsonPath('data.cover.typology', 'DEAM 1B')
            ->assertJsonPath('data.general_information.inspection_date', '2020-01-01T00:00:00+00:00')
            ->assertJsonPath('data.generated_document.file_name', 'VITÓRIA_DA_CONQUISTA_PM_1B_DEAM_1B.pdf')
            ->assertJsonPath('data.generated_document.generated_at', '2020-01-01T10:00:00+00:00')
            ->assertJsonMissingPath('data.generated_document.storage_identifier');
    })->with('report consultation roles');

    test('viewers receive 403 for drafts and generated versions awaiting or rejected by review', function (bool $generated, ?string $reviewStatus): void {
        $municipalities = reportConsultationCatalog();
        $reader = User::factory()->withRole(UserRoleEnum::VIEWER)->create();
        $author = User::factory()->create();
        $report = reportConsultationRecord($author, $municipalities['salvador'], generated: $generated);
        if ($reviewStatus !== null) {
            reportConsultationReview($report, $author, $reviewStatus);
        }
        $token = reportConsultationAccessToken($reader);

        $response = $this->withToken($token)->getJson('/api/reports/'.$report->id()->value());

        $response->assertForbidden()->assertJsonPath('message', 'Acesso não autorizado.')->assertJsonMissingPath('data.cover');
    })->with(['draft' => [false, null], 'pending review' => [true, 'PENDING'], 'rejected review' => [true, 'REJECTED']]);

    test('operators and reviewers receive 403 for private reports from another coordination', function (UserRoleEnum $role, bool $classified): void {
        $municipalities = reportConsultationCatalog();
        $own = reportConsultationCoordination('COTEC');
        $other = reportConsultationCoordination('OTHER');
        $reader = User::factory()->withRole($role)->create(['coordination_id' => $own['coordination_id']]);
        $author = User::factory()->create(['coordination_id' => $other['coordination_id']]);
        $report = reportConsultationRecord($author, $municipalities['salvador'], $classified ? $other['report_type_id'] : null, generated: false);
        $token = reportConsultationAccessToken($reader);

        $response = $this->withToken($token)->getJson('/api/reports/'.$report->id()->value());

        $response->assertForbidden();
    })->with([
        'operator typed report' => [UserRoleEnum::OPERATOR, true], 'reviewer typed report' => [UserRoleEnum::REVIEWER, true],
        'reviewer unclassified report' => [UserRoleEnum::REVIEWER, false],
    ]);

    test('operators receive 403 for another authors private report within their coordination', function (): void {
        $municipalities = reportConsultationCatalog();
        $coordination = reportConsultationCoordination('COTEC');
        $reader = User::factory()->create(['coordination_id' => $coordination['coordination_id']]);
        $author = User::factory()->create(['coordination_id' => $coordination['coordination_id']]);
        $report = reportConsultationRecord($author, $municipalities['salvador'], $coordination['report_type_id'], generated: false);
        $token = reportConsultationAccessToken($reader);

        $response = $this->withToken($token)->getJson('/api/reports/'.$report->id()->value());

        $response->assertForbidden();
    });

    test('reviewers read another authors pending review inside their own coordination', function (): void {
        $municipalities = reportConsultationCatalog();
        $coordination = reportConsultationCoordination('COTEC');
        $reader = User::factory()->withRole(UserRoleEnum::REVIEWER)->create(['coordination_id' => $coordination['coordination_id']]);
        $author = User::factory()->create(['coordination_id' => $coordination['coordination_id']]);
        $report = reportConsultationRecord($author, $municipalities['salvador'], $coordination['report_type_id']);
        reportConsultationReview($report, $reader, 'PENDING');
        $token = reportConsultationAccessToken($reader);

        $response = $this->withToken($token)->getJson('/api/reports/'.$report->id()->value());

        $response->assertOk()->assertJsonPath('data.id', $report->id()->value());
    });

    test('private report coordination remains stable after its author changes coordination', function (bool $originalCoordination, int $expectedStatus): void {
        $municipalities = reportConsultationCatalog();
        $own = reportConsultationCoordination('COTEC');
        $other = reportConsultationCoordination('OTHER');
        $author = User::factory()->create(['coordination_id' => $own['coordination_id']]);
        $report = reportConsultationRecord($author, $municipalities['salvador'], generated: false);
        $author->update(['coordination_id' => $other['coordination_id']]);
        $reader = User::factory()->withRole(UserRoleEnum::REVIEWER)->create([
            'coordination_id' => $originalCoordination ? $own['coordination_id'] : $other['coordination_id'],
        ]);
        $token = reportConsultationAccessToken($reader);

        $response = $this->withToken($token)->getJson('/api/reports/'.$report->id()->value());

        $response->assertStatus($expectedStatus);
        $this->assertDatabaseHas('reports', ['uuid' => $report->id()->value(), 'coordination_id' => $own['coordination_id']]);
    })->with(['original coordination retains access' => [true, 200], 'new author coordination gains no access' => [false, 403]]);

    test('superusers consult private reports from every coordination', function (): void {
        $municipalities = reportConsultationCatalog();
        $coordination = reportConsultationCoordination('OTHER');
        $reader = User::factory()->superuser()->create();
        $author = User::factory()->create(['coordination_id' => $coordination['coordination_id']]);
        $report = reportConsultationRecord($author, $municipalities['salvador'], $coordination['report_type_id'], generated: false);
        $token = reportConsultationAccessToken($reader);

        $response = $this->withToken($token)->getJson('/api/reports/'.$report->id()->value());

        $response->assertOk()->assertJsonPath('data.id', $report->id()->value())->assertJsonPath('data.status', 'DRAFT');
    });

    test('history excludes a private draft revision and latest returns 403 rather than exposing it to a viewer', function (string $suffix): void {
        $municipalities = reportConsultationCatalog();
        $coordination = reportConsultationCoordination('COTEC');
        $reader = User::factory()->withRole(UserRoleEnum::VIEWER)->create();
        $author = User::factory()->create(['coordination_id' => $coordination['coordination_id']]);
        $root = reportConsultationRecord($author, $municipalities['salvador'], $coordination['report_type_id']);
        app(ReportRepositoryInterface::class)->insert($root->createRevision($author->uuid));
        $token = reportConsultationAccessToken($reader);

        $response = $this->withToken($token)->getJson('/api/reports/'.$root->id()->value().'/'.$suffix);

        if ($suffix === 'history') {
            $response->assertOk()->assertJsonCount(1, 'data.reports')->assertJsonPath('data.reports.0.id', $root->id()->value());
        } else {
            $response->assertForbidden()->assertJsonMissingPath('data.id');
        }
    })->with(['history' => ['history'], 'latest private revision' => ['latest']]);

    test('legacy dashboard and counters cannot disclose other coordinations private reports', function (string $path, string $countPath): void {
        $municipalities = reportConsultationCatalog();
        $reader = User::factory()->withRole(UserRoleEnum::VIEWER)->create();
        $author = User::factory()->create();
        $public = reportConsultationRecord($author, $municipalities['salvador']);
        reportConsultationRecord($author, $municipalities['salvador'], generated: false);
        $pending = reportConsultationRecord($author, $municipalities['conquista']);
        reportConsultationReview($pending, $author, 'PENDING');
        $token = reportConsultationAccessToken($reader);

        $response = $this->withToken($token)->getJson($path);

        $response->assertOk()->assertJsonPath($countPath, 1);
        if ($path === '/api/reports/dashboard') {
            $response->assertJsonCount(1, 'data.reports')->assertJsonPath('data.reports.0.id', $public->id()->value());
        }
    })->with([
        'dashboard' => ['/api/reports/dashboard', 'data.total_reports'],
        'all count' => ['/api/reports/count', 'data.count'],
        'monthly count' => ['/api/reports/count-by-month?month=2020-01', 'data.counts.0.count'],
    ]);

    test('missing report identifiers return 404', function (string $suffix): void {
        $reader = User::factory()->withRole(UserRoleEnum::VIEWER)->create();
        $token = reportConsultationAccessToken($reader);

        $response = $this->withToken($token)->getJson('/api/reports/550e8400-e29b-41d4-a716-446655440099'.$suffix);

        $response->assertNotFound()->assertJsonPath('status', 'error');
    })->with(['metadata' => [''], 'PDF' => ['/pdf']]);
});

describe('report write permission regressions', function (): void {
    test('operators and reviewers create drafts in their own coordination with an explicit or inferred type', function (UserRoleEnum $role, bool $explicitType): void {
        $coordination = reportConsultationCoordination('COTEC');
        $writer = User::factory()->withRole($role)->create(['coordination_id' => $coordination['coordination_id']]);
        $token = reportConsultationAccessToken($writer);
        $payload = $explicitType ? ['report_type_id' => $coordination['report_type_id']] : [];

        $response = $this->withToken($token)->postJson('/api/reports', $payload);

        $response->assertCreated()->assertJsonPath('data.status', 'DRAFT');
        $this->assertDatabaseHas('reports', ['uuid' => $response->json('data.id'), 'created_by' => $writer->id, 'report_type_id' => $coordination['report_type_id'], 'status' => 'DRAFT']);
        $this->assertDatabaseCount('reports', 1);
    })->with([
        'operator explicit' => [UserRoleEnum::OPERATOR, true], 'operator inferred' => [UserRoleEnum::OPERATOR, false],
        'reviewer explicit' => [UserRoleEnum::REVIEWER, true], 'reviewer inferred' => [UserRoleEnum::REVIEWER, false],
    ]);

    test('superusers create reports for another coordination', function (): void {
        $coordination = reportConsultationCoordination('OTHER');
        $writer = User::factory()->superuser()->create();
        $token = reportConsultationAccessToken($writer);

        $response = $this->withToken($token)->postJson('/api/reports', ['report_type_id' => $coordination['report_type_id']]);

        $response->assertCreated();
        $this->assertDatabaseHas('reports', ['uuid' => $response->json('data.id'), 'created_by' => $writer->id, 'report_type_id' => $coordination['report_type_id']]);
    });

    test('viewers and writers without a coordination receive 403 and create no report', function (UserRoleEnum $role): void {
        $writer = User::factory()->withRole($role)->create();
        $token = reportConsultationAccessToken($writer);

        $response = $this->withToken($token)->postJson('/api/reports', []);

        $response->assertForbidden();
        $this->assertDatabaseEmpty('reports');
        $this->assertDatabaseEmpty('report_series');
    })->with(['viewer' => [UserRoleEnum::VIEWER], 'operator without coordination' => [UserRoleEnum::OPERATOR], 'reviewer without coordination' => [UserRoleEnum::REVIEWER]]);

    test('operators and reviewers receive 403 when selecting another coordinations report type', function (UserRoleEnum $role): void {
        $own = reportConsultationCoordination('COTEC');
        $other = reportConsultationCoordination('OTHER');
        $writer = User::factory()->withRole($role)->create(['coordination_id' => $own['coordination_id']]);
        $token = reportConsultationAccessToken($writer);

        $response = $this->withToken($token)->postJson('/api/reports', ['report_type_id' => $other['report_type_id']]);

        $response->assertForbidden();
        $this->assertDatabaseEmpty('reports');
    })->with(['operator' => [UserRoleEnum::OPERATOR], 'reviewer' => [UserRoleEnum::REVIEWER]]);

    test('cross coordination edits return 403 without changing the draft', function (UserRoleEnum $role): void {
        $municipalities = reportConsultationCatalog();
        $own = reportConsultationCoordination('COTEC');
        $other = reportConsultationCoordination('OTHER');
        $writer = User::factory()->withRole($role)->create(['coordination_id' => $own['coordination_id']]);
        $author = User::factory()->create(['coordination_id' => $other['coordination_id']]);
        $report = reportConsultationRecord($author, $municipalities['salvador'], $other['report_type_id'], generated: false);
        $token = reportConsultationAccessToken($writer);

        $response = $this->withToken($token)->patchJson('/api/reports/'.$report->id()->value(), ['cover' => ['typology' => 'Changed']]);

        $response->assertForbidden();
        $this->assertDatabaseHas('reports', ['uuid' => $report->id()->value(), 'typology' => 'DEAM 1B']);
        $this->assertDatabaseCount('reports', 1);
    })->with(['operator' => [UserRoleEnum::OPERATOR], 'reviewer' => [UserRoleEnum::REVIEWER], 'viewer' => [UserRoleEnum::VIEWER]]);

    test('cross coordination revision creation returns 403 even when the original is consultable', function (UserRoleEnum $role): void {
        $municipalities = reportConsultationCatalog();
        $own = reportConsultationCoordination('COTEC');
        $other = reportConsultationCoordination('OTHER');
        $writer = User::factory()->withRole($role)->create(['coordination_id' => $own['coordination_id']]);
        $author = User::factory()->create(['coordination_id' => $other['coordination_id']]);
        $report = reportConsultationRecord($author, $municipalities['salvador'], $other['report_type_id']);
        $token = reportConsultationAccessToken($writer);

        $response = $this->withToken($token)->postJson('/api/reports/'.$report->id()->value().'/revisions', []);

        $response->assertForbidden();
        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseCount('report_series', 1);
    })->with(['operator' => [UserRoleEnum::OPERATOR], 'reviewer' => [UserRoleEnum::REVIEWER], 'viewer' => [UserRoleEnum::VIEWER]]);

    test('reviewers edit another authors draft in their own coordination without changing its origin', function (): void {
        $municipalities = reportConsultationCatalog();
        $coordination = reportConsultationCoordination('COTEC');
        $writer = User::factory()->withRole(UserRoleEnum::REVIEWER)->create(['coordination_id' => $coordination['coordination_id']]);
        $author = User::factory()->create(['coordination_id' => $coordination['coordination_id']]);
        $report = reportConsultationRecord($author, $municipalities['salvador'], $coordination['report_type_id'], generated: false);
        $token = reportConsultationAccessToken($writer);

        $response = $this->withToken($token)->patchJson('/api/reports/'.$report->id()->value(), ['cover' => ['typology' => 'CIPM 1B']]);

        $response->assertOk()->assertJsonPath('data.cover.typology', 'CIPM 1B');
        $this->assertDatabaseHas('reports', [
            'uuid' => $report->id()->value(), 'created_by' => $author->id, 'typology' => 'CIPM 1B',
            'coordination_id' => $coordination['coordination_id'], 'report_type_id' => $coordination['report_type_id'],
        ]);
    });

    test('reviewers create a revision inside their own coordination preserving the report origin', function (): void {
        $municipalities = reportConsultationCatalog();
        $coordination = reportConsultationCoordination('COTEC');
        $writer = User::factory()->withRole(UserRoleEnum::REVIEWER)->create(['coordination_id' => $coordination['coordination_id']]);
        $author = User::factory()->create(['coordination_id' => $coordination['coordination_id']]);
        $report = reportConsultationRecord($author, $municipalities['salvador'], $coordination['report_type_id']);
        $token = reportConsultationAccessToken($writer);

        $response = $this->withToken($token)->postJson('/api/reports/'.$report->id()->value().'/revisions', []);

        $response->assertCreated()->assertJsonPath('data.revision_number', 1)->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.root_report_id', $report->id()->value());
        $this->assertDatabaseHas('reports', [
            'uuid' => $response->json('data.id'), 'created_by' => $writer->id, 'revision_number' => 1,
            'coordination_id' => $coordination['coordination_id'], 'report_type_id' => $coordination['report_type_id'],
        ]);
        $this->assertDatabaseCount('reports', 2);
        $this->assertDatabaseCount('report_series', 1);
    });

    test('a writer cannot forge origin or generated status while updating a report', function (): void {
        $municipalities = reportConsultationCatalog();
        $coordination = reportConsultationCoordination('COTEC');
        $author = User::factory()->create(['coordination_id' => $coordination['coordination_id']]);
        $report = reportConsultationRecord($author, $municipalities['salvador'], $coordination['report_type_id'], generated: false);
        $token = reportConsultationAccessToken($author);

        $response = $this->withToken($token)->patchJson('/api/reports/'.$report->id()->value(), [
            'cover' => ['typology' => 'Changed'], 'coordination_id' => 9999, 'report_type_id' => 9999, 'status' => 'GENERATED',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['coordination_id', 'report_type_id', 'status']);
        $this->assertDatabaseHas('reports', ['uuid' => $report->id()->value(), 'typology' => 'DEAM 1B', 'status' => 'DRAFT', 'coordination_id' => $coordination['coordination_id']]);
    });
});

test('coordination migration backfills type before author and keeps unknown legacy origins null', function (): void {
    $typedCoordination = reportConsultationCoordination('COTEC');
    $authorCoordination = reportConsultationCoordination('OTHER');
    $author = User::factory()->create(['coordination_id' => $authorCoordination['coordination_id']]);
    $unassignedAuthor = User::factory()->create();
    $migration = require database_path('migrations/Report/2026_10_09_191130_add_coordination_id_to_reports.php');
    $migration->down();
    $typed = Report::factory()->create(['created_by' => $author->id, 'report_type_id' => $typedCoordination['report_type_id']]);
    $untyped = Report::factory()->create(['created_by' => $author->id]);
    $unknown = Report::factory()->create(['created_by' => $unassignedAuthor->id]);
    foreach ([$typed, $untyped, $unknown] as $report) {
        $payload = $report->payload;
        unset($payload['coordinationId'], $payload['reportTypeId']);
        $report->update(['payload' => $payload]);
    }

    if (DB::connection()->getDriverName() === 'sqlite') {
        DB::statement('PRAGMA defer_foreign_keys = ON');
    }
    $migration->up();

    $this->assertDatabaseHas('reports', ['id' => $typed->id, 'coordination_id' => $typedCoordination['coordination_id']]);
    $this->assertDatabaseHas('reports', ['id' => $untyped->id, 'coordination_id' => $authorCoordination['coordination_id']]);
    $this->assertDatabaseHas('reports', ['id' => $unknown->id, 'coordination_id' => null]);
    expect($typed->refresh()->payload['coordinationId'])->toBe($typedCoordination['coordination_id']);
    expect($typed->payload['reportTypeId'])->toBe($typedCoordination['report_type_id']);
    expect($untyped->refresh()->payload['coordinationId'])->toBe($authorCoordination['coordination_id']);
    expect($unknown->refresh()->payload['coordinationId'])->toBeNull();

    $migration->down();

    expect(Schema::hasColumn('reports', 'coordination_id'))->toBeFalse();
    $this->assertDatabaseCount('reports', 3);
    $this->assertDatabaseHas('reports', ['id' => $typed->id, 'report_type_id' => $typedCoordination['report_type_id']]);
    $migration->up();
});

test('report reads return 401 when authentication is absent', function (string $path): void {
    $response = $this->getJson($path);

    $response->assertUnauthorized()->assertJsonPath('message', 'Não autenticado.');
})->with([
    'metadata' => ['/api/reports/550e8400-e29b-41d4-a716-446655440099'],
    'download' => ['/api/reports/550e8400-e29b-41d4-a716-446655440099/pdf'],
]);
