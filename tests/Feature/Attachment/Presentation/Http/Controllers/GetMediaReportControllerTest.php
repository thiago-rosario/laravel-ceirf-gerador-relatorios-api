<?php

declare(strict_types=1);

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use src\Modules\Attachment\Application\Exception\ReportMediaUnavailableException;
use src\Modules\Identity\Domain\Enum\UserRoleEnum;
use src\Modules\Identity\Model\User;
use src\Modules\Report\Domain\Entity\ReportAttachmentEntity;
use src\Modules\Report\Domain\Entity\ReportEntity;
use src\Modules\Report\Domain\Entity\ReportImageEntity;
use src\Modules\Report\Domain\Enum\ForceEnum;
use src\Modules\Report\Domain\Enum\ReportAttachmentTypeEnum;
use src\Modules\Report\Domain\Enum\ReportSizeEnum;
use src\Modules\Report\Domain\Repository\ReportRepositoryInterface;
use src\Modules\Report\Domain\ValueObject\GeneratedReportValueObject;
use src\Modules\Report\Domain\ValueObject\MunicipalityValueObject;
use src\Modules\Report\Domain\ValueObject\ReportAttachmentsValueObject;
use src\Modules\Report\Domain\ValueObject\ReportCoverValueObject;
use src\Modules\Report\Domain\ValueObject\ReportFileReferenceValueObject;
use src\Modules\Report\Domain\ValueObject\ReportLocationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPhotographicDocumentationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPreImplementationValueObject;
use src\Modules\Report\Domain\ValueObject\SeiNumberValueObject;
use src\Modules\Report\Model\Report;
use Tests\Fixtures\ReportApplicationFixtures;

function getReportMediaAccessToken(User $user): string
{
    $response = test()->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password']);
    $response->assertOk();
    Auth::forgetGuards();

    return $response->json('data.access_token');
}

function getReportMediaContents(string $extension = 'png'): string
{
    if ($extension === 'pdf') {
        return "%PDF-1.7\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    }

    return UploadedFile::fake()->image('terrain.'.$extension, 1, 1)->get();
}

function getReportMediaImage(string $reportId, string $extension = 'png', ?string $fileName = null, ?string $path = null): ReportImageEntity
{
    $mediaId = (string) Str::uuid();
    $contents = getReportMediaContents($extension);
    $path ??= 'reports/'.$reportId.'/media/'.$mediaId.'.'.$extension;
    Storage::disk('local')->put($path, $contents, ['visibility' => 'private']);

    return new ReportImageEntity(
        file: new ReportFileReferenceValueObject(
            $path, $fileName ?? 'terrain.'.$extension,
            $extension === 'pdf' ? 'application/pdf' : ($extension === 'png' ? 'image/png' : 'image/jpeg'),
            strlen($contents), hash('sha256', $contents),
        ),
        order: 1,
        caption: 'Vista do terreno',
        id: $mediaId,
    );
}

/** @param array<string, mixed> $overrides */
function getReportMediaRecord(User $author, ReportImageEntity $image, bool $generated = false, array $overrides = []): ReportEntity
{
    DB::table('identity_territories')->insertOrIgnore(['name' => 'Território de teste']);
    $territoryId = DB::table('identity_territories')->where('name', 'Território de teste')->value('id');
    DB::table('municipalities')->insertOrIgnore([
        'identity_territory_id' => $territoryId, 'name' => 'Salvador', 'state_code' => 'BA',
    ]);
    $municipalityId = (int) DB::table('municipalities')->where('name', 'Salvador')->value('id');
    DB::table('forces')->insertOrIgnore(['code' => 'PM', 'name' => 'Polícia Militar']);
    DB::table('sizes')->insertOrIgnore(['name' => '1B']);
    $report = ReportApplicationFixtures::completeReport(array_replace([
        'id' => explode('/', $image->file()->storageIdentifier())[1],
        'createdBy' => $author->uuid,
        'coordinationId' => $author->coordination_id,
        'cover' => new ReportCoverValueObject(
            municipality: new MunicipalityValueObject($municipalityId, 'Salvador'),
            force: ForceEnum::PM, size: ReportSizeEnum::ONE_B, typology: 'Delegacia',
            seiNumber: new SeiNumberValueObject('012.3456.2020.0000012-34'),
        ),
        'location' => new ReportLocationValueObject(municipalityInStateMap: $image),
    ], $overrides));
    if ($generated) {
        $report->registerGeneratedDocument(new GeneratedReportValueObject(
            storageIdentifier: 'reports/'.$report->id()->value().'/documents/'.Str::uuid().'.pdf',
            fileName: $report->fileName(), generatedAt: new DateTimeImmutable('2020-01-01 10:00:00'),
        ));
    }

    return app(ReportRepositoryInterface::class)->insert($report);
}

function getReportMediaRevision(ReportEntity $parent, User $author, ?ReportImageEntity $image = null, bool $generated = false): ReportEntity
{
    $revision = $parent->createRevision($author->uuid);
    if ($image !== null) {
        $revision->changeLocation(new ReportLocationValueObject(municipalityInStateMap: $image));
    }
    if ($generated) {
        $revision->registerGeneratedDocument(new GeneratedReportValueObject(
            storageIdentifier: 'reports/'.$revision->id()->value().'/documents/'.Str::uuid().'.pdf',
            fileName: $revision->fileName(), generatedAt: new DateTimeImmutable('2020-01-02 10:00:00'),
        ));
    }

    return app(ReportRepositoryInterface::class)->insert($revision);
}

test('returns exact private bytes with the correct MIME type and preview headers', function (string $extension, string $mimeType): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $image = getReportMediaImage((string) Str::uuid(), $extension);
    $contents = Storage::disk('local')->get($image->file()->storageIdentifier());
    $report = getReportMediaRecord($author, $image);
    $token = getReportMediaAccessToken($author);

    $response = $this->withToken($token)->get('/api/reports/'.$report->id()->value().'/media/'.$image->id()->value());

    $response->assertOk()->assertHeader('Content-Type', $mimeType)
        ->assertHeader('Content-Length', (string) strlen($contents))
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->streamedContent())->toBe($contents);
    expect($response->headers->get('Content-Disposition'))->toStartWith('inline;')->toContain('terrain.'.$extension)
        ->not->toContain($image->file()->storageIdentifier());
    $response->assertHeaderMissing('Location');
})->with([
    'PNG' => ['png', 'image/png'], 'JPG' => ['jpg', 'image/jpeg'],
    'JPEG' => ['jpeg', 'image/jpeg'], 'PDF' => ['pdf', 'application/pdf'],
]);

test('honors inline and attachment dispositions while sanitizing filenames', function (string $disposition): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $image = getReportMediaImage((string) Str::uuid(), 'pdf', '../private\\planta "final".pdf');
    $report = getReportMediaRecord($author, $image);
    $token = getReportMediaAccessToken($author);

    $response = $this->withToken($token)->get('/api/reports/'.$report->id()->value().'/media/'.$image->id()->value().'?disposition='.$disposition);

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->streamedContent())->toBe(getReportMediaContents('pdf'));
    expect($response->headers->get('Content-Disposition'))->toStartWith($disposition.';')->toContain('.pdf')
        ->not->toContain('../')->not->toContain('private')->not->toContain($image->file()->storageIdentifier());
})->with(['inline', 'attachment']);

test('returns 401 JSend without a valid Bearer token', function (?string $token): void {
    $headers = $token === null ? [] : ['Authorization' => 'Bearer '.$token];

    $response = $this->getJson('/api/reports/'.Str::uuid().'/media/'.Str::uuid(), $headers);

    $response->assertUnauthorized()->assertJsonPath('status', 'error')->assertJsonPath('message', 'Não autenticado.');
})->with(['missing token' => [null], 'invalid token' => ['invalid-bearer-token']]);

test('returns 403 JSend when the requested report is not readable', function (): void {
    Storage::fake('local');
    $author = User::factory()->create();
    $reader = User::factory()->withRole(UserRoleEnum::VIEWER)->create();
    $image = getReportMediaImage((string) Str::uuid());
    $report = getReportMediaRecord($author, $image);
    $token = getReportMediaAccessToken($reader);

    $response = $this->withToken($token)->getJson('/api/reports/'.$report->id()->value().'/media/'.$image->id()->value());

    $response->assertForbidden()->assertJsonPath('status', 'error')->assertJsonPath('message', 'Acesso não autorizado.');
    expect($response->getContent())->not->toContain($image->file()->storageIdentifier());
});

test('returns 403 until the initial password is changed', function (): void {
    $reader = User::factory()->superuser()->mustChangePassword()->create();
    $token = getReportMediaAccessToken($reader);

    $response = $this->withToken($token)->getJson('/api/reports/'.Str::uuid().'/media/'.Str::uuid());

    $response->assertForbidden()->assertJsonPath('status', 'error')->assertJsonPath('data.must_change_password', true);
});

test('returns 404 JSend for a missing report, unknown media or missing private file', function (string $missing): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $image = getReportMediaImage((string) Str::uuid());
    $report = getReportMediaRecord($author, $image);
    $reportId = $missing === 'report' ? (string) Str::uuid() : $report->id()->value();
    $mediaId = $missing === 'media' ? (string) Str::uuid() : $image->id()->value();
    if ($missing === 'file') {
        Storage::disk('local')->delete($image->file()->storageIdentifier());
    }
    $token = getReportMediaAccessToken($author);

    $response = $this->withToken($token)->getJson('/api/reports/'.$reportId.'/media/'.$mediaId);

    $response->assertNotFound()->assertJsonPath('status', 'error');
    expect($response->getContent())->not->toContain($image->file()->storageIdentifier());
})->with(['report', 'media', 'file']);

test('returns 422 JSend validation errors for malformed identifiers or disposition', function (string $reportId, string $mediaId, string $query, string $field): void {
    $author = User::factory()->superuser()->create();
    $token = getReportMediaAccessToken($author);

    $response = $this->withToken($token)->getJson('/api/reports/'.$reportId.'/media/'.$mediaId.$query);

    $response->assertUnprocessable()->assertJsonPath('status', 'fail')
        ->assertJsonPath('message', 'Os dados informados são inválidos.')
        ->assertJsonStructure(['data' => ['errors' => [$field]]]);
})->with([
    'malformed report UUID' => ['not-a-uuid', '550e8400-e29b-41d4-a716-446655440001', '', 'id'],
    'malformed media UUID' => ['550e8400-e29b-41d4-a716-446655440000', 'not-a-uuid', '', 'mediaId'],
    'unsupported disposition' => ['550e8400-e29b-41d4-a716-446655440000', '550e8400-e29b-41d4-a716-446655440001', '?disposition=executable', 'disposition'],
    'array disposition' => ['550e8400-e29b-41d4-a716-446655440000', '550e8400-e29b-41d4-a716-446655440001', '?disposition[]=inline', 'disposition'],
]);

test('resolves active references from every report media section returned by GET report', function (string $section): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $image = getReportMediaImage((string) Str::uuid());
    $attachmentType = match ($section) {
        'attachments.municipality_location_map' => ReportAttachmentTypeEnum::MUNICIPALITY_LOCATION_MAP,
        'attachments.topographic_plan' => ReportAttachmentTypeEnum::TOPOGRAPHIC_PLAN,
        default => ReportAttachmentTypeEnum::OTHER,
    };
    $attachment = new ReportAttachmentEntity($attachmentType, $image->file(), 'Documento de vistoria', $image->id());
    $overrides = ['location' => null];
    $overrides = array_replace($overrides, match ($section) {
        'location.location_map' => ['location' => new ReportLocationValueObject(locationMap: $image)],
        'location.municipality_in_state_map' => ['location' => new ReportLocationValueObject(municipalityInStateMap: $image)],
        'pre_implementation.image' => ['preImplementation' => new ReportPreImplementationValueObject($image)],
        'photographic_documentation.images.0' => ['photographicDocumentation' => new ReportPhotographicDocumentationValueObject([$image])],
        'attachments.municipality_location_map' => ['attachments' => new ReportAttachmentsValueObject(municipalityLocationMap: $attachment)],
        'attachments.topographic_plan' => ['attachments' => new ReportAttachmentsValueObject(topographicPlan: $attachment)],
        default => ['attachments' => new ReportAttachmentsValueObject(others: [$attachment])],
    });
    $report = getReportMediaRecord($author, $image, overrides: $overrides);
    $token = getReportMediaAccessToken($author);
    $metadata = $this->withToken($token)->getJson('/api/reports/'.$report->id()->value());
    $metadata->assertOk()->assertJsonPath('data.'.$section.'.id', $image->id()->value());

    $response = $this->withToken($token)->get('/api/reports/'.$metadata->json('data.id').'/media/'.$metadata->json('data.'.$section.'.id'));

    $response->assertOk();
    expect($response->streamedContent())->toBe(Storage::disk('local')->get($image->file()->storageIdentifier()));
})->with([
    'location.location_map', 'location.municipality_in_state_map', 'pre_implementation.image',
    'photographic_documentation.images.0', 'attachments.municipality_location_map',
    'attachments.topographic_plan', 'attachments.others.0',
]);

test('returns 404 for another report family even when the same user may read both reports', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $first = getReportMediaRecord($author, getReportMediaImage((string) Str::uuid()));
    $foreignImage = getReportMediaImage((string) Str::uuid());
    getReportMediaRecord($author, $foreignImage);
    $token = getReportMediaAccessToken($author);

    $response = $this->withToken($token)->getJson('/api/reports/'.$first->id()->value().'/media/'.$foreignImage->id()->value());

    $response->assertNotFound()->assertJsonPath('status', 'error');
    expect($response->getContent())->not->toContain($foreignImage->file()->storageIdentifier());
});

test('resolves legacy image and document identifiers from GET report across the same revision family', function (string $extension, string $section): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $root = getReportMediaRecord($author, getReportMediaImage((string) Str::uuid()), generated: true);
    $image = getReportMediaImage($root->id()->value(), $extension);
    $revision = $root->createRevision($author->uuid);
    if ($extension === 'pdf') {
        $revision->changeAttachments(new ReportAttachmentsValueObject(others: [new ReportAttachmentEntity(
            ReportAttachmentTypeEnum::OTHER, $image->file(), 'Planta do terreno', $image->id(),
        )]));
    } else {
        $revision->changeLocation(new ReportLocationValueObject(municipalityInStateMap: $image));
    }
    $revision = app(ReportRepositoryInterface::class)->insert($revision);
    if ($extension !== 'pdf') {
        DB::table('report_images')->where('uuid', $image->id()->value())->update(['type' => 'municipality_in_state_map']);
    }
    Report::query()->where('uuid', $revision->id()->value())->update(['payload' => null]);
    $token = getReportMediaAccessToken($author);
    $metadata = $this->withToken($token)->getJson('/api/reports/'.$revision->id()->value());
    $metadata->assertOk()->assertJsonPath('data.'.$section.'.id', $image->id()->value());

    $response = $this->withToken($token)->get('/api/reports/'.$root->id()->value().'/media/'.$metadata->json('data.'.$section.'.id'));

    $response->assertOk();
    expect($response->streamedContent())->toBe(Storage::disk('local')->get($image->file()->storageIdentifier()));
})->with([
    'legacy image' => ['jpg', 'location.municipality_in_state_map'],
    'legacy document' => ['pdf', 'attachments.others.0'],
]);

test('permits active media of a readable revision in the requested report family', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $reader = User::factory()->withRole(UserRoleEnum::VIEWER)->create();
    $rootImage = getReportMediaImage((string) Str::uuid());
    $root = getReportMediaRecord($author, $rootImage, generated: true);
    $revisionImage = getReportMediaImage($root->id()->value(), 'pdf');
    getReportMediaRevision($root, $author, $revisionImage, generated: true);
    $token = getReportMediaAccessToken($reader);

    $response = $this->withToken($token)->get('/api/reports/'.$root->id()->value().'/media/'.$revisionImage->id()->value());

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->streamedContent())->toBe(getReportMediaContents('pdf'));
});

test('returns 404 for active media found only in an unreadable draft revision', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $reader = User::factory()->withRole(UserRoleEnum::VIEWER)->create();
    $root = getReportMediaRecord($author, getReportMediaImage((string) Str::uuid()), generated: true);
    $draftImage = getReportMediaImage($root->id()->value(), 'pdf');
    getReportMediaRevision($root, $author, $draftImage);
    $token = getReportMediaAccessToken($reader);

    $response = $this->withToken($token)->getJson('/api/reports/'.$root->id()->value().'/media/'.$draftImage->id()->value());

    $response->assertNotFound()->assertJsonPath('status', 'error');
    expect($response->getContent())->not->toContain($draftImage->file()->storageIdentifier());
});

test('permits inherited active media even when its original revision is unreadable', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $reader = User::factory()->withRole(UserRoleEnum::VIEWER)->create();
    $image = getReportMediaImage((string) Str::uuid());
    $root = getReportMediaRecord($author, $image, generated: true);
    $revision = getReportMediaRevision($root, $author, generated: true);
    DB::table('report_reviews')->insert([
        'report_id' => Report::query()->where('uuid', $root->id()->value())->value('id'),
        'reviewer_id' => $author->id, 'status' => 'PENDING', 'created_at' => '2020-01-01 10:00:00',
    ]);
    $token = getReportMediaAccessToken($reader);
    $this->withToken($token)->getJson('/api/reports/'.$root->id()->value())->assertForbidden();

    $response = $this->withToken($token)->get('/api/reports/'.$revision->id()->value().'/media/'.$image->id()->value());

    $response->assertOk();
    expect($response->streamedContent())->toBe(Storage::disk('local')->get($image->file()->storageIdentifier()));
});

test('exposes historical images only to users who can edit their owning report', function (bool $editor): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $reader = $editor ? $author : User::factory()->withRole(UserRoleEnum::VIEWER)->create();
    $oldImage = getReportMediaImage((string) Str::uuid());
    $currentImage = getReportMediaImage(explode('/', $oldImage->file()->storageIdentifier())[1], 'pdf');
    $report = getReportMediaRecord($author, $currentImage, generated: true, overrides: ['uploadedImages' => [$oldImage]]);
    $token = getReportMediaAccessToken($reader);

    $response = $this->withToken($token)->getJson('/api/reports/'.$report->id()->value().'/media/'.$oldImage->id()->value());

    if ($editor) {
        $response->assertOk();
        expect($response->streamedContent())->toBe(Storage::disk('local')->get($oldImage->file()->storageIdentifier()));
    } else {
        $response->assertNotFound()->assertJsonPath('status', 'error');
    }
})->with(['editor' => [true], 'viewer' => [false]]);

test('restricts pending uploads of a draft revision to its editors when requested through a consultable ancestor', function (bool $editor): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $root = getReportMediaRecord($author, getReportMediaImage((string) Str::uuid()), generated: true);
    $draft = getReportMediaRevision($root, $author);
    $authorToken = getReportMediaAccessToken($author);
    $contents = getReportMediaContents('pdf');
    $upload = $this->withToken($authorToken)->post('/api/reports/'.$draft->id()->value().'/media', [
        'file' => UploadedFile::fake()->createWithContent('survey.pdf', $contents)->mimeType('application/pdf'),
    ], ['Accept' => 'application/json']);
    $upload->assertCreated();
    $reader = $editor ? $author : User::factory()->withRole(UserRoleEnum::VIEWER)->create();
    $token = getReportMediaAccessToken($reader);

    $response = $this->withToken($token)->getJson('/api/reports/'.$root->id()->value().'/media/'.$upload->json('data.id'));

    if ($editor) {
        $response->assertOk();
        expect($response->streamedContent())->toBe($contents);
    } else {
        $response->assertNotFound()->assertJsonPath('status', 'error');
        expect($response->getContent())->not->toContain($upload->json('data.file.storage_identifier'));
    }
})->with(['editor' => [true], 'viewer' => [false]]);

test('returns 422 JSend when stored bytes are invalid or do not match their declared type', function (string $contents, bool $matchingChecksum): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $image = getReportMediaImage((string) Str::uuid());
    Storage::disk('local')->put($image->file()->storageIdentifier(), $contents);
    if ($matchingChecksum) {
        $image = new ReportImageEntity(new ReportFileReferenceValueObject(
            $image->file()->storageIdentifier(), 'terrain.png', 'image/png', strlen($contents), hash('sha256', $contents),
        ), 1, id: $image->id());
    }
    $report = getReportMediaRecord($author, $image);
    $token = getReportMediaAccessToken($author);

    $response = $this->withToken($token)->getJson('/api/reports/'.$report->id()->value().'/media/'.$image->id()->value());

    $response->assertUnprocessable()->assertJsonPath('status', 'error');
    expect($response->getContent())->not->toContain($image->file()->storageIdentifier())->not->toContain('secret-marker');
})->with([
    'PHP disguised as PNG with matching checksum' => ['<?php echo "secret-marker";', true],
    'PDF declared as PNG with matching checksum' => ["%PDF-1.7\nsecret-marker\n%%EOF\n", true],
    'modified image' => ['modified bytes', false],
    'empty file' => ['', false],
]);

test('returns 404 instead of exposing private paths outside the report family', function (string $path): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $reportId = (string) Str::uuid();
    $path = str_replace('{report}', $reportId, $path);
    $image = getReportMediaImage($reportId, path: $path);
    $report = getReportMediaRecord($author, $image, overrides: ['id' => $reportId]);
    $token = getReportMediaAccessToken($author);

    $response = $this->withToken($token)->getJson('/api/reports/'.$report->id()->value().'/media/'.$image->id()->value());

    $response->assertNotFound()->assertJsonPath('status', 'error');
    expect($response->getContent())->not->toContain($path);
})->with([
    'arbitrary private file' => ['private/secret.png'],
    'another family storage' => ['reports/550e8400-e29b-41d4-a716-446655440099/media/550e8400-e29b-41d4-a716-446655440098.png'],
    'parent directory traversal' => ['reports/{report}/media/../secret.png'],
]);

test('returns 422 for modified bytes that preserve the expected size and MIME type', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $image = getReportMediaImage((string) Str::uuid());
    $report = getReportMediaRecord($author, $image);
    $contents = Storage::disk('local')->get($image->file()->storageIdentifier());
    $contents[strlen($contents) - 1] = chr(ord($contents[strlen($contents) - 1]) ^ 1);
    Storage::disk('local')->put($image->file()->storageIdentifier(), $contents);
    $token = getReportMediaAccessToken($author);

    $response = $this->withToken($token)->getJson('/api/reports/'.$report->id()->value().'/media/'.$image->id()->value());

    $response->assertUnprocessable()->assertJsonPath('status', 'error');
});

test('returns 500 JSend and reports a storage failure without leaking its details', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $image = getReportMediaImage((string) Str::uuid());
    $report = getReportMediaRecord($author, $image);
    $token = getReportMediaAccessToken($author);
    $failure = new RuntimeException('Private storage failed at /private/secret/terrain.png');
    Exceptions::fake();
    Storage::shouldReceive('disk')->with('local')->andThrow($failure);

    $response = $this->withToken($token)->getJson('/api/reports/'.$report->id()->value().'/media/'.$image->id()->value());

    $response->assertInternalServerError()->assertJsonPath('status', 'error');
    expect($response->getContent())->not->toContain('/private/secret')->not->toContain($image->file()->storageIdentifier());
    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception === $failure);
});

test('returns 500 JSend when storage cannot open an existing file stream', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $image = getReportMediaImage((string) Str::uuid());
    $report = getReportMediaRecord($author, $image);
    $token = getReportMediaAccessToken($author);
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('exists')->once()->with($image->file()->storageIdentifier())->andReturn(true);
    $disk->shouldReceive('readStream')->once()->with($image->file()->storageIdentifier())->andReturn(null);
    Storage::shouldReceive('disk')->once()->with('local')->andReturn($disk);
    Exceptions::fake();

    $response = $this->withToken($token)->getJson('/api/reports/'.$report->id()->value().'/media/'.$image->id()->value());

    $response->assertInternalServerError()->assertJsonPath('status', 'error');
    expect($response->getContent())->not->toContain($image->file()->storageIdentifier());
    Exceptions::assertReported(ReportMediaUnavailableException::class);
});
