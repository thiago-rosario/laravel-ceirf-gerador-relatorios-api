<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use src\Modules\Identity\Domain\Enum\UserRoleEnum;
use src\Modules\Identity\Model\User;
use src\Modules\Report\Domain\Entity\ReportAttachmentEntity;
use src\Modules\Report\Domain\Entity\ReportEntity;
use src\Modules\Report\Domain\Entity\ReportImageEntity;
use src\Modules\Report\Domain\Enum\ReportAttachmentTypeEnum;
use src\Modules\Report\Domain\Repository\ReportRepositoryInterface;
use src\Modules\Report\Domain\ValueObject\ReportAttachmentsValueObject;
use src\Modules\Report\Domain\ValueObject\ReportFileReferenceValueObject;
use src\Modules\Report\Domain\ValueObject\ReportLocationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPhotographicDocumentationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPreImplementationValueObject;
use src\Modules\Report\Infra\Mapper\ReportPersistenceMapper;
use src\Modules\Report\Model\Report;

function attachmentMediaAccessToken(User $user): string
{
    $response = test()->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password']);
    $response->assertOk();
    Auth::forgetGuards();

    return $response->json('data.access_token');
}

function attachmentMediaContents(string $format = 'png', string $suffix = ''): string
{
    $contents = match ($format) {
        'pdf' => "%PDF-1.7\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n",
        'jpeg' => base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD//gA7Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2NjIpLCBxdWFsaXR5ID0gNTAK/9sAQwAQCwwODAoQDg0OEhEQExgoGhgWFhgxIyUdKDozPTw5Mzg3QEhcTkBEV0U3OFBtUVdfYmdoZz5NcXlwZHhcZWdj/9sAQwEREhIYFRgvGhovY0I4QmNjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2Nj/8AAEQgAAQABAwEiAAIRAQMRAf/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29/j5+v/aAAwDAQACEQMRAD8A8/ooooA//9k=', true),
        default => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jY9sAAAAASUVORK5CYII=', true),
    };

    return $contents.$suffix;
}

function attachmentMediaFile(string $name = 'figure.png', ?string $contents = null): UploadedFile
{
    $file = UploadedFile::fake()->createWithContent($name, $contents ?? attachmentMediaContents());
    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());

    return $file->mimeType($mimeType);
}

function attachmentMediaFullReport(User $author): ReportEntity
{
    $reportId = (string) Str::uuid();
    $images = [];

    foreach (range(1, 19) as $position) {
        $identifier = (string) Str::uuid();
        $path = 'reports/'.$reportId.'/media/'.$identifier.'.png';
        $contents = attachmentMediaContents(suffix: (string) $position);
        Storage::disk('local')->put($path, $contents, ['visibility' => 'private']);
        $images[] = new ReportImageEntity(
            file: new ReportFileReferenceValueObject($path, 'figure-'.$position.'.png', 'image/png', strlen($contents), hash('sha256', $contents)),
            order: $position,
            caption: 'Figura '.$position,
            id: $identifier,
        );
    }

    $attachmentId = (string) Str::uuid();
    $attachmentPath = 'reports/'.$reportId.'/media/'.$attachmentId.'.pdf';
    $attachmentContents = attachmentMediaContents('pdf');
    Storage::disk('local')->put($attachmentPath, $attachmentContents, ['visibility' => 'private']);

    return app(ReportRepositoryInterface::class)->insert(new ReportEntity(
        createdBy: $author->uuid,
        id: $reportId,
        location: new ReportLocationValueObject($images[0], $images[1]),
        preImplementation: new ReportPreImplementationValueObject($images[2]),
        photographicDocumentation: new ReportPhotographicDocumentationValueObject(array_slice($images, 3)),
        attachments: new ReportAttachmentsValueObject(others: [new ReportAttachmentEntity(
            type: ReportAttachmentTypeEnum::OTHER,
            file: new ReportFileReferenceValueObject($attachmentPath, 'survey.pdf', 'application/pdf', strlen($attachmentContents), hash('sha256', $attachmentContents)),
            description: 'Levantamento do terreno',
            id: $attachmentId,
        )]),
    ));
}

test('stores approved file bytes privately and returns authoritative metadata without linking the report', function (string $name, string $format, string $mimeType, string $extension): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $report = Report::factory()->create(['created_by' => $author->id]);
    $originalPayload = $report->payload;
    $contents = attachmentMediaContents($format);
    $token = attachmentMediaAccessToken($author);

    $response = $this->withToken($token)->post('/api/reports/'.$report->uuid.'/media', [
        'file' => attachmentMediaFile($name, $contents),
        'checksum' => strtoupper(hash('sha256', $contents)),
        'root_report_id' => (string) Str::uuid(),
        'uploaded_by' => 999999,
        'storage_identifier' => '../../public/unsafe.php',
    ], ['Accept' => 'application/json']);

    $response->assertCreated()->assertJsonPath('status', 'success')
        ->assertJsonPath('data.root_report_id', $report->uuid)
        ->assertJsonPath('data.file.file_name', $name)
        ->assertJsonPath('data.file.mime_type', $mimeType)
        ->assertJsonPath('data.file.size_bytes', strlen($contents))
        ->assertJsonPath('data.file.checksum', hash('sha256', $contents));
    $mediaId = $response->json('data.id');
    expect($mediaId)->toBeUuid();
    $path = 'reports/'.$report->uuid.'/media/'.$mediaId.'.'.$extension;
    $response->assertJsonPath('data.file.storage_identifier', $path);
    Storage::disk('local')->assertExists($path, $contents);
    expect(Storage::disk('local')->getVisibility($path))->toBe('private');
    expect($report->fresh()->payload)->toBe($originalPayload);
    $this->assertDatabaseHas('report_media_uploads', [
        'uuid' => $mediaId, 'report_id' => $report->id, 'root_report_id' => $report->uuid,
        'uploaded_by' => $author->id, 'storage_identifier' => $path, 'mime_type' => $mimeType,
        'size_bytes' => strlen($contents), 'checksum' => hash('sha256', $contents), 'linked_at' => null,
    ]);
    $this->assertDatabaseEmpty('report_images');
    $this->assertDatabaseEmpty('report_attachments');
})->with([
    'PNG' => ['terrain.png', 'png', 'image/png', 'png'],
    'JPG' => ['terrain.jpg', 'jpeg', 'image/jpeg', 'jpg'],
    'JPEG' => ['terrain.jpeg', 'jpeg', 'image/jpeg', 'jpg'],
    'PDF' => ['survey.pdf', 'pdf', 'application/pdf', 'pdf'],
]);

test('returns 401 without a valid Bearer token and stores no file', function (?string $token): void {
    Storage::fake('local');
    $report = Report::factory()->create();
    $headers = ['Accept' => 'application/json'];
    if ($token !== null) {
        $headers['Authorization'] = 'Bearer '.$token;
    }

    $response = $this->post('/api/reports/'.$report->uuid.'/media', ['file' => attachmentMediaFile()], $headers);

    $response->assertUnauthorized()->assertJsonPath('message', 'Não autenticado.');
    $this->assertDatabaseEmpty('report_media_uploads');
    Storage::disk('local')->assertDirectoryEmpty('/');
})->with(['missing token' => [null], 'invalid token' => ['invalid-bearer-token']]);

test('returns 403 when the authenticated profile cannot modify the report', function (string $scenario): void {
    Storage::fake('local');
    $coordinationId = DB::table('coordinations')->insertGetId(['code' => 'COTEC', 'name' => 'COTEC']);
    $otherCoordinationId = DB::table('coordinations')->insertGetId(['code' => 'OTHER', 'name' => 'OTHER']);
    $author = User::factory()->create(['coordination_id' => $coordinationId]);
    $reader = $scenario === 'viewer'
        ? User::factory()->withRole(UserRoleEnum::VIEWER)->create(['coordination_id' => $coordinationId])
        : User::factory()->withRole(UserRoleEnum::REVIEWER)->create(['coordination_id' => $otherCoordinationId]);
    $report = Report::factory()->create(['created_by' => $author->id, 'coordination_id' => $coordinationId]);
    $token = attachmentMediaAccessToken($reader);

    $response = $this->withToken($token)->post('/api/reports/'.$report->uuid.'/media', ['file' => attachmentMediaFile()], ['Accept' => 'application/json']);

    $response->assertForbidden()->assertJsonPath('message', 'Acesso não autorizado.');
    $this->assertDatabaseEmpty('report_media_uploads');
    Storage::disk('local')->assertDirectoryEmpty('/');
})->with(['viewer' => ['viewer'], 'different coordination' => ['different coordination']]);

test('returns 403 until the authenticated user changes the initial password', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->mustChangePassword()->create();
    $report = Report::factory()->create(['created_by' => $author->id]);
    $token = attachmentMediaAccessToken($author);

    $response = $this->withToken($token)->post('/api/reports/'.$report->uuid.'/media', ['file' => attachmentMediaFile()], ['Accept' => 'application/json']);

    $response->assertForbidden()->assertJsonPath('data.must_change_password', true);
    $this->assertDatabaseEmpty('report_media_uploads');
    Storage::disk('local')->assertDirectoryEmpty('/');
});

test('returns 404 for a missing report without storing a file', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $token = attachmentMediaAccessToken($author);

    $response = $this->withToken($token)->post('/api/reports/'.Str::uuid().'/media', ['file' => attachmentMediaFile()], ['Accept' => 'application/json']);

    $response->assertNotFound()->assertJsonPath('status', 'error');
    $this->assertDatabaseEmpty('report_media_uploads');
    Storage::disk('local')->assertDirectoryEmpty('/');
});

test('returns 409 for a generated report without storing a file', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $report = Report::factory()->create(['created_by' => $author->id, 'status' => 'GENERATED']);
    $token = attachmentMediaAccessToken($author);

    $response = $this->withToken($token)->post('/api/reports/'.$report->uuid.'/media', ['file' => attachmentMediaFile()], ['Accept' => 'application/json']);

    $response->assertConflict()->assertJsonPath('code', 2003)
        ->assertJsonPath('message', 'Um relatório já gerado não pode ser alterado. Crie uma nova revisão.');
    $this->assertDatabaseEmpty('report_media_uploads');
    Storage::disk('local')->assertDirectoryEmpty('/');
});

test('returns 422 when no file is provided', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $report = Report::factory()->create(['created_by' => $author->id]);
    $token = attachmentMediaAccessToken($author);

    $response = $this->withToken($token)->postJson('/api/reports/'.$report->uuid.'/media', []);

    $response->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->assertDatabaseEmpty('report_media_uploads');
    Storage::disk('local')->assertDirectoryEmpty('/');
});

test('returns 422 for disguised or unsupported file content', function (string $name, string $contents): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $report = Report::factory()->create(['created_by' => $author->id]);
    $token = attachmentMediaAccessToken($author);

    $response = $this->withToken($token)->post('/api/reports/'.$report->uuid.'/media', ['file' => attachmentMediaFile($name, $contents)], ['Accept' => 'application/json']);

    $response->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->assertDatabaseEmpty('report_media_uploads');
    Storage::disk('local')->assertDirectoryEmpty('/');
})->with([
    'PHP disguised as PNG' => ['figure.png', '<?php echo "unsafe";'],
    'plain text disguised as PDF' => ['survey.pdf', 'This is not a PDF document.'],
    'SVG' => ['figure.svg', '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>'],
    'GIF' => ['figure.gif', 'GIF89a'.str_repeat("\0", 100)],
]);

test('returns 422 when the file extension is not permitted despite approved content', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $report = Report::factory()->create(['created_by' => $author->id]);
    $token = attachmentMediaAccessToken($author);

    $response = $this->withToken($token)->post('/api/reports/'.$report->uuid.'/media', ['file' => attachmentMediaFile('unsafe.php', attachmentMediaContents('pdf'))], ['Accept' => 'application/json']);

    $response->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->assertDatabaseEmpty('report_media_uploads');
    Storage::disk('local')->assertDirectoryEmpty('/');
});

test('accepts a file of exactly 25 MiB and links it through the existing PATCH', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $report = Report::factory()->create(['created_by' => $author->id]);
    $contents = str_pad(attachmentMediaContents('pdf'), 26214400, ' ');
    $token = attachmentMediaAccessToken($author);

    $response = $this->withToken($token)->post('/api/reports/'.$report->uuid.'/media', ['file' => attachmentMediaFile('survey.pdf', $contents)], ['Accept' => 'application/json']);

    $response->assertCreated()->assertJsonPath('data.file.size_bytes', 26214400);
    $storagePath = $response->json('data.file.storage_identifier');
    Storage::disk('local')->assertExists($storagePath);
    expect(hash_file('sha256', Storage::disk('local')->path($storagePath)))->toBe(hash('sha256', $contents));
    $this->assertDatabaseCount('report_media_uploads', 1);

    $attachment = ['id' => $response->json('data.id'), 'file' => $response->json('data.file'), 'description' => 'Planta topográfica'];
    $this->withToken($token)->patchJson('/api/reports/'.$report->uuid, ['attachments' => ['others' => [$attachment]]])
        ->assertOk()->assertJsonPath('data.attachments.others.0.file', $attachment['file']);
    $this->assertDatabaseHas('report_attachments', ['uuid' => $attachment['id'], 'size_bytes' => 26214400]);
});

test('returns 422 for a file one byte larger than 25 MiB', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $report = Report::factory()->create(['created_by' => $author->id]);
    $contents = str_pad(attachmentMediaContents('pdf'), 26214401, ' ');
    $token = attachmentMediaAccessToken($author);

    $response = $this->withToken($token)->post('/api/reports/'.$report->uuid.'/media', ['file' => attachmentMediaFile('survey.pdf', $contents)], ['Accept' => 'application/json']);

    $response->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->assertDatabaseEmpty('report_media_uploads');
    Storage::disk('local')->assertDirectoryEmpty('/');
});

test('returns 422 when the supplied SHA-256 does not match the file', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $report = Report::factory()->create(['created_by' => $author->id]);
    $token = attachmentMediaAccessToken($author);

    $response = $this->withToken($token)->post('/api/reports/'.$report->uuid.'/media', ['file' => attachmentMediaFile(), 'checksum' => str_repeat('0', 64)], ['Accept' => 'application/json']);

    $response->assertUnprocessable()->assertJsonPath('code', 2011)
        ->assertJsonPath('message', 'O checksum SHA-256 não corresponde ao arquivo enviado.');
    $this->assertDatabaseEmpty('report_media_uploads');
    Storage::disk('local')->assertDirectoryEmpty('/');
});

test('returns 422 for malformed upload metadata', function (array $metadata, string $field): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $report = Report::factory()->create(['created_by' => $author->id]);
    $token = attachmentMediaAccessToken($author);

    $response = $this->withToken($token)->post('/api/reports/'.$report->uuid.'/media', ['file' => attachmentMediaFile(), ...$metadata], ['Accept' => 'application/json']);

    $response->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseEmpty('report_media_uploads');
    Storage::disk('local')->assertDirectoryEmpty('/');
})->with([
    'invalid SHA-256' => [['checksum' => 'not-a-sha256'], 'checksum'],
    'invalid replacement UUID' => [['replaces_id' => 'not-a-uuid'], 'replaces_id'],
]);

test('returns 422 for duplicate bytes regardless of the uploaded name', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $report = Report::factory()->create(['created_by' => $author->id]);
    $token = attachmentMediaAccessToken($author);
    $original = $this->withToken($token)->post('/api/reports/'.$report->uuid.'/media', ['file' => attachmentMediaFile('original.png')], ['Accept' => 'application/json']);
    $original->assertCreated();

    $response = $this->withToken($token)->post('/api/reports/'.$report->uuid.'/media', ['file' => attachmentMediaFile('renamed.png')], ['Accept' => 'application/json']);

    $response->assertUnprocessable()->assertJsonPath('code', 2014)
        ->assertJsonPath('message', 'O relatório não pode conter arquivos duplicados.');
    $this->assertDatabaseCount('report_media_uploads', 1);
    Storage::disk('local')->assertExists($original->json('data.file.storage_identifier'), attachmentMediaContents());
    expect(Storage::disk('local')->allFiles())->toHaveCount(1);
});

test('accepts identical bytes in different report families', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $first = Report::factory()->create(['created_by' => $author->id]);
    $second = Report::factory()->create(['created_by' => $author->id]);
    $token = attachmentMediaAccessToken($author);
    $this->withToken($token)->post('/api/reports/'.$first->uuid.'/media', ['file' => attachmentMediaFile()], ['Accept' => 'application/json'])->assertCreated();

    $response = $this->withToken($token)->post('/api/reports/'.$second->uuid.'/media', ['file' => attachmentMediaFile()], ['Accept' => 'application/json']);

    $response->assertCreated()->assertJsonPath('data.root_report_id', $second->uuid);
    $this->assertDatabaseCount('report_media_uploads', 2);
    Storage::disk('local')->assertExists($response->json('data.file.storage_identifier'));
});

test('returns 422 for a duplicate of legacy image or document media without adding an upload', function (string $format, string $suffix, string $name): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $report = attachmentMediaFullReport($author);
    $token = attachmentMediaAccessToken($author);

    $response = $this->withToken($token)->post('/api/reports/'.$report->id()->value().'/media', [
        'file' => attachmentMediaFile($name, attachmentMediaContents($format, $suffix)),
    ], ['Accept' => 'application/json']);

    $response->assertUnprocessable()->assertJsonPath('code', 2014);
    $this->assertDatabaseEmpty('report_media_uploads');
    expect(Storage::disk('local')->allFiles())->toHaveCount(20);
})->with([
    'legacy image' => ['png', '1', 'renamed.png'],
    'legacy document' => ['pdf', '', 'renamed.pdf'],
]);

test('stores revision uploads in their root family and rejects a duplicate from the parent', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $root = Report::factory()->create(['created_by' => $author->id]);
    $revisionId = (string) Str::uuid();
    $revision = Report::factory()->create([
        'uuid' => $revisionId, 'created_by' => $author->id, 'report_series_id' => $root->report_series_id,
        'previous_report_id' => $root->id, 'revision_number' => 1,
        'payload' => app(ReportPersistenceMapper::class)->toPayload(new ReportEntity(
            createdBy: $author->uuid, id: $revisionId, rootReportId: $root->uuid,
            parentReportId: $root->uuid, revisionNumber: 1,
        )),
    ]);
    $token = attachmentMediaAccessToken($author);
    $this->withToken($token)->post('/api/reports/'.$root->uuid.'/media', ['file' => attachmentMediaFile()], ['Accept' => 'application/json'])->assertCreated();

    $duplicate = $this->withToken($token)->post('/api/reports/'.$revision->uuid.'/media', ['file' => attachmentMediaFile()], ['Accept' => 'application/json']);
    $response = $this->withToken($token)->post('/api/reports/'.$revision->uuid.'/media', ['file' => attachmentMediaFile('revised.png', attachmentMediaContents(suffix: 'revision'))], ['Accept' => 'application/json']);

    $duplicate->assertUnprocessable()->assertJsonPath('code', 2014);
    $response->assertCreated()->assertJsonPath('data.root_report_id', $root->uuid);
    Storage::disk('local')->assertExists('reports/'.$root->uuid.'/media/'.$response->json('data.id').'.png');
    $this->assertDatabaseHas('report_media_uploads', ['uuid' => $response->json('data.id'), 'report_id' => $revision->id, 'root_report_id' => $root->uuid]);
});

test('returns metadata that the existing PATCH links without alteration', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $report = Report::factory()->create(['created_by' => $author->id]);
    $token = attachmentMediaAccessToken($author);
    $upload = $this->withToken($token)->post('/api/reports/'.$report->uuid.'/media', ['file' => attachmentMediaFile()], ['Accept' => 'application/json']);
    $upload->assertCreated();
    $media = ['id' => $upload->json('data.id'), 'file' => $upload->json('data.file'), 'order' => 1, 'caption' => 'Vista do terreno'];

    $response = $this->withToken($token)->patchJson('/api/reports/'.$report->uuid, ['photographic_documentation' => ['images' => [$media]]]);

    $response->assertOk()->assertJsonPath('data.photographic_documentation.images.0', $media);
    $this->assertDatabaseHas('report_images', ['report_id' => $report->id, 'uuid' => $media['id'], 'file_hash' => $media['file']['checksum']]);
    $this->assertDatabaseMissing('report_media_uploads', ['uuid' => $media['id'], 'linked_at' => null]);
    Storage::disk('local')->assertExists($media['file']['storage_identifier']);
});

test('returns 422 after twenty unlinked uploads without leaving another file', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $report = Report::factory()->create(['created_by' => $author->id]);
    $token = attachmentMediaAccessToken($author);
    foreach (range(1, 20) as $position) {
        $this->withToken($token)->post('/api/reports/'.$report->uuid.'/media', ['file' => attachmentMediaFile('figure.png', attachmentMediaContents(suffix: (string) $position))], ['Accept' => 'application/json'])->assertCreated();
    }

    $response = $this->withToken($token)->post('/api/reports/'.$report->uuid.'/media', ['file' => attachmentMediaFile('overflow.png', attachmentMediaContents(suffix: 'overflow'))], ['Accept' => 'application/json']);

    $response->assertUnprocessable()->assertJsonPath('code', 2015)
        ->assertJsonPath('message', 'O relatório deve possuir no máximo 20 figuras, desconsiderando as imagens fixas da capa.');
    $this->assertDatabaseCount('report_media_uploads', 20);
    expect(Storage::disk('local')->allFiles())->toHaveCount(20);
});

test('returns 422 when twenty active figures span all report sections including PDF attachments', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $report = attachmentMediaFullReport($author);
    $token = attachmentMediaAccessToken($author);

    $response = $this->withToken($token)->post('/api/reports/'.$report->id()->value().'/media', ['file' => attachmentMediaFile('overflow.png', attachmentMediaContents(suffix: 'overflow'))], ['Accept' => 'application/json']);

    $response->assertUnprocessable()->assertJsonPath('code', 2015);
    $this->assertDatabaseEmpty('report_media_uploads');
    expect(Storage::disk('local')->allFiles())->toHaveCount(20);
});

test('replaces an active figure at the global limit through a new upload and preserves its previous bytes', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $report = attachmentMediaFullReport($author);
    $oldImage = $report->location()->locationMap();
    $token = attachmentMediaAccessToken($author);
    $upload = $this->withToken($token)->post('/api/reports/'.$report->id()->value().'/media', [
        'file' => attachmentMediaFile('replacement.png', attachmentMediaContents(suffix: 'replacement')),
        'replaces_id' => $oldImage->id()->value(),
    ], ['Accept' => 'application/json']);
    $upload->assertCreated();
    $municipalityImage = $report->location()->municipalityInStateMap();

    $response = $this->withToken($token)->patchJson('/api/reports/'.$report->id()->value(), ['location' => [
        'location_map' => ['id' => $upload->json('data.id'), 'file' => $upload->json('data.file'), 'order' => 1],
        'municipality_in_state_map' => [
            'id' => $municipalityImage->id()->value(), 'order' => $municipalityImage->order(),
            'file' => [
                'storage_identifier' => $municipalityImage->file()->storageIdentifier(), 'file_name' => $municipalityImage->file()->fileName(),
                'mime_type' => $municipalityImage->file()->mimeType(), 'size_bytes' => $municipalityImage->file()->sizeBytes(),
                'checksum' => $municipalityImage->file()->checksum(),
            ],
        ],
    ]]);

    $response->assertOk()->assertJsonPath('data.location.location_map.id', $upload->json('data.id'));
    Storage::disk('local')->assertExists($oldImage->file()->storageIdentifier(), attachmentMediaContents(suffix: '1'));
    Storage::disk('local')->assertExists($upload->json('data.file.storage_identifier'));
    $this->assertDatabaseHas('report_images', ['uuid' => $oldImage->id()->value(), 'file_hash' => $oldImage->file()->checksum()]);
    $this->assertDatabaseMissing('report_media_uploads', ['uuid' => $upload->json('data.id'), 'linked_at' => null]);
});

test('returns 422 when a replacement UUID does not identify an active media of this report', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $report = Report::factory()->create(['created_by' => $author->id]);
    $token = attachmentMediaAccessToken($author);

    $response = $this->withToken($token)->post('/api/reports/'.$report->uuid.'/media', ['file' => attachmentMediaFile(), 'replaces_id' => (string) Str::uuid()], ['Accept' => 'application/json']);

    $response->assertUnprocessable();
    $this->assertDatabaseEmpty('report_media_uploads');
    Storage::disk('local')->assertDirectoryEmpty('/');
});

test('returns 422 when an active figure already has a pending replacement', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $report = attachmentMediaFullReport($author);
    $replacesId = $report->location()->locationMap()->id()->value();
    $token = attachmentMediaAccessToken($author);
    $first = $this->withToken($token)->post('/api/reports/'.$report->id()->value().'/media', [
        'file' => attachmentMediaFile('first.png', attachmentMediaContents(suffix: 'first replacement')),
        'replaces_id' => $replacesId,
    ], ['Accept' => 'application/json']);
    $first->assertCreated();

    $response = $this->withToken($token)->post('/api/reports/'.$report->id()->value().'/media', [
        'file' => attachmentMediaFile('second.png', attachmentMediaContents(suffix: 'second replacement')),
        'replaces_id' => $replacesId,
    ], ['Accept' => 'application/json']);

    $response->assertUnprocessable()->assertJsonPath('code', 2011)
        ->assertJsonPath('message', 'Já existe um upload pendente para substituir esta mídia.');
    $this->assertDatabaseCount('report_media_uploads', 1);
    Storage::disk('local')->assertExists($first->json('data.file.storage_identifier'));
    expect(Storage::disk('local')->allFiles())->toHaveCount(21);
});

test('counts a pending replacement as a new slot after its original figure is removed', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $report = attachmentMediaFullReport($author);
    $token = attachmentMediaAccessToken($author);
    $this->withToken($token)->post('/api/reports/'.$report->id()->value().'/media', [
        'file' => attachmentMediaFile('replacement.png', attachmentMediaContents(suffix: 'pending replacement')),
        'replaces_id' => $report->location()->locationMap()->id()->value(),
    ], ['Accept' => 'application/json'])->assertCreated();
    $remainingImage = $report->location()->municipalityInStateMap();
    $this->withToken($token)->patchJson('/api/reports/'.$report->id()->value(), ['location' => [
        'municipality_in_state_map' => [
            'id' => $remainingImage->id()->value(), 'order' => $remainingImage->order(),
            'file' => [
                'storage_identifier' => $remainingImage->file()->storageIdentifier(), 'file_name' => $remainingImage->file()->fileName(),
                'mime_type' => $remainingImage->file()->mimeType(), 'size_bytes' => $remainingImage->file()->sizeBytes(),
                'checksum' => $remainingImage->file()->checksum(),
            ],
        ],
    ]])->assertOk();

    $response = $this->withToken($token)->post('/api/reports/'.$report->id()->value().'/media', [
        'file' => attachmentMediaFile('overflow.png', attachmentMediaContents(suffix: 'overflow')),
    ], ['Accept' => 'application/json']);

    $response->assertUnprocessable()->assertJsonPath('code', 2015);
    $this->assertDatabaseCount('report_media_uploads', 1);
    expect(Storage::disk('local')->allFiles())->toHaveCount(21);
});

test('preserves replaced document uploads even after PATCH removes their active references', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $report = Report::factory()->create(['created_by' => $author->id]);
    $token = attachmentMediaAccessToken($author);
    $originalContents = attachmentMediaContents('pdf', 'original');
    $original = $this->withToken($token)->post('/api/reports/'.$report->uuid.'/media', ['file' => attachmentMediaFile('original.pdf', $originalContents)], ['Accept' => 'application/json']);
    $original->assertCreated();
    $this->withToken($token)->patchJson('/api/reports/'.$report->uuid, ['attachments' => ['others' => [
        ['id' => $original->json('data.id'), 'file' => $original->json('data.file'), 'description' => 'Planta original'],
    ]]])->assertOk();
    $replacement = $this->withToken($token)->post('/api/reports/'.$report->uuid.'/media', [
        'file' => attachmentMediaFile('replacement.pdf', attachmentMediaContents('pdf', 'replacement')),
        'replaces_id' => $original->json('data.id'),
    ], ['Accept' => 'application/json']);
    $replacement->assertCreated();

    $response = $this->withToken($token)->patchJson('/api/reports/'.$report->uuid, ['attachments' => ['others' => [
        ['id' => $replacement->json('data.id'), 'file' => $replacement->json('data.file'), 'description' => 'Planta atualizada'],
    ]]]);

    $response->assertOk()->assertJsonPath('data.attachments.others.0.id', $replacement->json('data.id'));
    $this->assertDatabaseCount('report_media_uploads', 2);
    $this->assertDatabaseHas('report_media_uploads', ['uuid' => $original->json('data.id'), 'checksum' => hash('sha256', $originalContents)]);
    $this->assertDatabaseMissing('report_media_uploads', ['uuid' => $original->json('data.id'), 'linked_at' => null]);
    $this->assertDatabaseMissing('report_media_uploads', ['uuid' => $replacement->json('data.id'), 'linked_at' => null]);
    $this->assertDatabaseMissing('report_attachments', ['uuid' => $original->json('data.id')]);
    Storage::disk('local')->assertExists($original->json('data.file.storage_identifier'), $originalContents);
    Storage::disk('local')->assertExists($replacement->json('data.file.storage_identifier'));
});

test('returns 500 and removes the newly stored file when upload persistence fails', function (): void {
    Storage::fake('local');
    $author = User::factory()->superuser()->create();
    $report = Report::factory()->create(['created_by' => $author->id]);
    $token = attachmentMediaAccessToken($author);
    DB::unprepared("CREATE TRIGGER reject_report_media_upload BEFORE INSERT ON report_media_uploads BEGIN SELECT RAISE(ABORT, 'upload persistence unavailable'); END");
    Exceptions::fake();

    $response = $this->withToken($token)->post('/api/reports/'.$report->uuid.'/media', ['file' => attachmentMediaFile()], ['Accept' => 'application/json']);

    $response->assertInternalServerError()->assertJsonPath('status', 'error');
    $this->assertDatabaseEmpty('report_media_uploads');
    Storage::disk('local')->assertDirectoryEmpty('/');
    Exceptions::assertReported(QueryException::class);
});
