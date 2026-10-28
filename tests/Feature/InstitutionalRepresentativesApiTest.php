<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use src\Modules\Identity\Domain\Enum\UserRoleEnum;
use src\Modules\Identity\Model\User;
use src\Modules\Report\Model\Report;

function institutionalRepresentativesAccessToken(User $user): string
{
    $response = test()->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password']);
    $response->assertOk();
    Auth::forgetGuards();

    return $response->json('data.access_token');
}

/** @return list<array{name: string, position: string}> */
function institutionalRepresentatives(): array
{
    return [
        ['name' => 'Maria dos Santos', 'position' => 'Secretária Municipal'],
        ['name' => 'José Oliveira', 'position' => 'Coordenador de Infraestrutura'],
    ];
}

function institutionalRepresentativesReport(User $author): Report
{
    $report = Report::factory()->create(['created_by' => $author->id, 'coordination_id' => $author->coordination_id]);
    $payload = $report->payload;
    $payload['coordinationId'] = $author->coordination_id;
    $payload['generalInformation'] = [
        'inspectionDate' => '2020-01-01T00:00:00+00:00',
        'collaborators' => 'Ana Silva e João Santos',
        'institutionalRepresentatives' => institutionalRepresentatives(),
    ];
    $report->update([
        'inspection_date' => '2020-01-01',
        'present_collaborators' => 'Ana Silva e João Santos',
        'payload' => $payload,
    ]);

    return $report;
}

test('creates an initial autosave draft without general information or representatives', function (): void {
    $author = User::factory()->superuser()->create();
    $token = institutionalRepresentativesAccessToken($author);

    $response = $this->withToken($token)->postJson('/api/reports', []);

    $response->assertCreated()->assertJsonPath('status', 'success')->assertJsonPath('data.status', 'DRAFT');
    $this->assertDatabaseHas('reports', ['uuid' => $response->json('data.id'), 'created_by' => $author->id]);
    $this->withToken($token)->getJson('/api/reports/'.$response->json('data.id'))
        ->assertOk()->assertJsonPath('data.general_information', null);
});

test('creates general information without requiring institutional representatives', function (array $additionalInformation): void {
    $author = User::factory()->superuser()->create();
    $token = institutionalRepresentativesAccessToken($author);

    $response = $this->withToken($token)->postJson('/api/reports', [
        'general_information' => [
            'inspection_date' => '2020-01-01',
            'collaborators' => 'Ana Silva e João Santos',
            ...$additionalInformation,
        ],
    ]);

    $response->assertCreated()->assertJsonPath('status', 'success');
    $this->assertDatabaseHas('reports', [
        'uuid' => $response->json('data.id'),
        'present_collaborators' => 'Ana Silva e João Santos',
        'inspection_date' => '2020-01-01',
    ]);
    $this->withToken($token)->getJson('/api/reports/'.$response->json('data.id'))
        ->assertOk()->assertJsonPath('data.general_information.institutional_representatives', [])
        ->assertJsonPath('data.general_information.collaborators', 'Ana Silva e João Santos');
})->with(['omitted collection' => [[]], 'empty collection' => [['institutional_representatives' => []]]]);

test('creates and retrieves multiple representatives with their positions independently of collaborators', function (): void {
    $author = User::factory()->superuser()->create();
    $token = institutionalRepresentativesAccessToken($author);
    $representatives = institutionalRepresentatives();

    $response = $this->withToken($token)->postJson('/api/reports', [
        'general_information' => [
            'inspection_date' => '2020-01-01',
            'collaborators' => 'Ana Silva e João Santos',
            'institutional_representatives' => $representatives,
        ],
    ]);

    $response->assertCreated()->assertJsonPath('status', 'success');
    $report = Report::query()->where('uuid', $response->json('data.id'))->firstOrFail();
    expect($report->payload['generalInformation']['institutionalRepresentatives'])->toBe($representatives);
    $this->assertDatabaseHas('reports', ['uuid' => $report->uuid, 'present_collaborators' => 'Ana Silva e João Santos']);
    $this->withToken($token)->getJson('/api/reports/'.$report->uuid)
        ->assertOk()->assertJsonPath('status', 'success')
        ->assertJsonPath('data.general_information.institutional_representatives', $representatives)
        ->assertJsonPath('data.general_information.collaborators', 'Ana Silva e João Santos');
});

test('replaces the representatives collection to add edit or remove members while preserving the other general information', function (array $representatives): void {
    $author = User::factory()->superuser()->create();
    $report = institutionalRepresentativesReport($author);
    $token = institutionalRepresentativesAccessToken($author);

    $response = $this->withToken($token)->patchJson('/api/reports/'.$report->uuid, [
        'general_information' => ['institutional_representatives' => $representatives],
    ]);

    $response->assertOk()->assertJsonPath('status', 'success')
        ->assertJsonPath('data.general_information.institutional_representatives', $representatives)
        ->assertJsonPath('data.general_information.collaborators', 'Ana Silva e João Santos')
        ->assertJsonPath('data.general_information.inspection_date', '2020-01-01T00:00:00+00:00');
    expect($report->refresh()->payload['generalInformation']['institutionalRepresentatives'])->toBe($representatives);
    $this->assertDatabaseHas('reports', [
        'uuid' => $report->uuid, 'present_collaborators' => 'Ana Silva e João Santos', 'inspection_date' => '2020-01-01',
    ]);
})->with([
    'adds a representative' => [[
        ['name' => 'Maria dos Santos', 'position' => 'Secretária Municipal'],
        ['name' => 'José Oliveira', 'position' => 'Coordenador de Infraestrutura'],
        ['name' => 'Joana Souza', 'position' => 'Engenheira Civil'],
    ]],
    'edits a name and position' => [[
        ['name' => 'Maria Santos Pereira', 'position' => 'Secretária de Obras'],
        ['name' => 'José Oliveira', 'position' => 'Coordenador de Infraestrutura'],
    ]],
    'removes one representative' => [[['name' => 'Maria dos Santos', 'position' => 'Secretária Municipal']]],
    'removes all representatives' => [[]],
]);

test('preserves representatives during partial updates and autosaves that omit the collection', function (array $changes, string $collaborators, ?string $inspectionDate): void {
    $author = User::factory()->superuser()->create();
    $report = institutionalRepresentativesReport($author);
    $token = institutionalRepresentativesAccessToken($author);

    $response = $this->withToken($token)->patchJson('/api/reports/'.$report->uuid, $changes);

    $response->assertOk()->assertJsonPath('status', 'success')
        ->assertJsonPath('data.general_information.institutional_representatives', institutionalRepresentatives())
        ->assertJsonPath('data.general_information.collaborators', $collaborators)
        ->assertJsonPath('data.general_information.inspection_date', $inspectionDate);
    expect($report->refresh()->payload['generalInformation']['institutionalRepresentatives'])->toBe(institutionalRepresentatives());
    $this->assertDatabaseHas('reports', ['uuid' => $report->uuid, 'present_collaborators' => $collaborators]);
})->with([
    'another section' => [['conclusion' => ['content' => 'Texto salvo automaticamente.']], 'Ana Silva e João Santos', '2020-01-01T00:00:00+00:00'],
    'collaborators only' => [['general_information' => ['collaborators' => 'Ana Silva']], 'Ana Silva', '2020-01-01T00:00:00+00:00'],
    'inspection date only' => [['general_information' => ['inspection_date' => '2020-02-02']], 'Ana Silva e João Santos', '2020-02-02T00:00:00+00:00'],
    'empty general information' => [['general_information' => []], 'Ana Silva e João Santos', '2020-01-01T00:00:00+00:00'],
    'explicitly clears existing text and date' => [['general_information' => ['collaborators' => null, 'inspection_date' => null]], '', null],
]);

test('retrieves and upgrades reports saved before institutional representatives existed', function (bool $hasSnapshot): void {
    $author = User::factory()->superuser()->create();
    $report = institutionalRepresentativesReport($author);
    $payload = $report->payload;
    unset($payload['generalInformation']['institutionalRepresentatives']);
    $report->update(['payload' => $hasSnapshot ? $payload : null]);
    $token = institutionalRepresentativesAccessToken($author);

    $this->withToken($token)->getJson('/api/reports/'.$report->uuid)
        ->assertOk()->assertJsonPath('status', 'success')
        ->assertJsonPath('data.general_information.institutional_representatives', [])
        ->assertJsonPath('data.general_information.collaborators', 'Ana Silva e João Santos');

    $response = $this->withToken($token)->patchJson('/api/reports/'.$report->uuid, [
        'general_information' => ['institutional_representatives' => institutionalRepresentatives()],
    ]);

    $response->assertOk()->assertJsonPath('data.general_information.institutional_representatives', institutionalRepresentatives())
        ->assertJsonPath('data.general_information.collaborators', 'Ana Silva e João Santos')
        ->assertJsonPath('data.general_information.inspection_date', '2020-01-01T00:00:00+00:00');
    expect($report->refresh()->payload['generalInformation']['institutionalRepresentatives'])->toBe(institutionalRepresentatives());
})->with(['old snapshot' => [true], 'legacy relational draft' => [false]]);

test('rejects invalid institutional representative input with 422 and does not create a report', function (mixed $representatives, string $field): void {
    $author = User::factory()->superuser()->create();
    $token = institutionalRepresentativesAccessToken($author);

    $response = $this->withToken($token)->postJson('/api/reports', [
        'general_information' => ['institutional_representatives' => $representatives],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseEmpty('reports');
    $this->assertDatabaseEmpty('report_series');
})->with([
    'null collection' => [null, 'general_information.institutional_representatives'],
    'scalar collection' => ['Maria', 'general_information.institutional_representatives'],
    'associative collection' => [['representative' => ['name' => 'Maria', 'position' => 'Secretária']], 'general_information.institutional_representatives'],
    'scalar representative' => [['Maria'], 'general_information.institutional_representatives.0'],
    'missing name' => [[['position' => 'Secretária']], 'general_information.institutional_representatives.0.name'],
    'missing position' => [[['name' => 'Maria']], 'general_information.institutional_representatives.0.position'],
    'blank name' => [[['name' => '   ', 'position' => 'Secretária']], 'general_information.institutional_representatives.0.name'],
    'blank position' => [[['name' => 'Maria', 'position' => '   ']], 'general_information.institutional_representatives.0.position'],
    'non string name' => [[['name' => 123, 'position' => 'Secretária']], 'general_information.institutional_representatives.0.name'],
    'non string position' => [[['name' => 'Maria', 'position' => 123]], 'general_information.institutional_representatives.0.position'],
    'name above the limit' => [[['name' => str_repeat('a', 256), 'position' => 'Secretária']], 'general_information.institutional_representatives.0.name'],
    'position above the limit' => [[['name' => 'Maria', 'position' => str_repeat('a', 256)]], 'general_information.institutional_representatives.0.position'],
    'unexpected member attribute' => [[['name' => 'Maria', 'position' => 'Secretária', 'report_id' => 999]], 'general_information.institutional_representatives.0'],
]);

test('rejects an invalid representative update with 422 without replacing saved representatives', function (): void {
    $author = User::factory()->superuser()->create();
    $report = institutionalRepresentativesReport($author);
    $originalPayload = $report->payload;
    $token = institutionalRepresentativesAccessToken($author);

    $response = $this->withToken($token)->patchJson('/api/reports/'.$report->uuid, [
        'general_information' => ['institutional_representatives' => [['name' => 'Maria']]],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('general_information.institutional_representatives.0.position');
    expect($report->refresh()->payload)->toBe($originalPayload);
});

test('returns 401 for representative operations without authentication', function (string $method, string $uri): void {
    $response = $this->json($method, $uri, ['general_information' => ['institutional_representatives' => institutionalRepresentatives()]]);

    $response->assertUnauthorized()->assertJsonPath('status', 'error')->assertJsonPath('message', 'Não autenticado.');
    $this->assertDatabaseEmpty('reports');
})->with([
    'creation' => ['POST', '/api/reports'],
    'consultation' => ['GET', '/api/reports/550e8400-e29b-41d4-a716-446655440099'],
    'update' => ['PATCH', '/api/reports/550e8400-e29b-41d4-a716-446655440099'],
]);

test('returns 403 when a viewer attempts to create representatives', function (): void {
    $reader = User::factory()->withRole(UserRoleEnum::VIEWER)->create();
    $token = institutionalRepresentativesAccessToken($reader);

    $response = $this->withToken($token)->postJson('/api/reports', [
        'general_information' => ['institutional_representatives' => institutionalRepresentatives()],
    ]);

    $response->assertForbidden()->assertJsonPath('status', 'error')->assertJsonPath('message', 'Acesso não autorizado.');
    $this->assertDatabaseEmpty('reports');
});

test('returns 403 for unauthorized representative changes and keeps the saved collection', function (UserRoleEnum $role): void {
    $ownCoordination = DB::table('coordinations')->insertGetId(['code' => 'COTEC', 'name' => 'COTEC']);
    $otherCoordination = DB::table('coordinations')->insertGetId(['code' => 'OTHER', 'name' => 'OTHER']);
    $author = User::factory()->create(['coordination_id' => $ownCoordination]);
    $report = institutionalRepresentativesReport($author);
    $originalPayload = $report->payload;
    $reader = User::factory()->withRole($role)->create(['coordination_id' => $otherCoordination]);
    $token = institutionalRepresentativesAccessToken($reader);

    $response = $this->withToken($token)->patchJson('/api/reports/'.$report->uuid, [
        'general_information' => ['institutional_representatives' => []],
    ]);

    $response->assertForbidden()->assertJsonPath('status', 'error')->assertJsonPath('message', 'Acesso não autorizado.');
    expect($report->refresh()->payload)->toBe($originalPayload);
})->with(['viewer' => [UserRoleEnum::VIEWER], 'operator from another coordination' => [UserRoleEnum::OPERATOR]]);

test('creates representatives at the multibyte text limit without requiring collaborators or an inspection date', function (): void {
    $author = User::factory()->superuser()->create();
    $token = institutionalRepresentativesAccessToken($author);
    $representatives = [['name' => str_repeat('á', 255), 'position' => str_repeat('ç', 255)]];

    $response = $this->withToken($token)->postJson('/api/reports', [
        'general_information' => ['institutional_representatives' => $representatives],
    ]);

    $response->assertCreated()->assertJsonPath('status', 'success');
    $report = Report::query()->where('uuid', $response->json('data.id'))->firstOrFail();
    expect($report->payload['generalInformation']['institutionalRepresentatives'])->toBe($representatives);
    $this->withToken($token)->getJson('/api/reports/'.$report->uuid)
        ->assertOk()->assertJsonPath('data.general_information.institutional_representatives', $representatives)
        ->assertJsonPath('data.general_information.collaborators', '')
        ->assertJsonPath('data.general_information.inspection_date', null);
});

test('returns 422 for a representative name containing only Unicode whitespace on creation', function (): void {
    $author = User::factory()->superuser()->create();
    $token = institutionalRepresentativesAccessToken($author);

    $response = $this->withToken($token)->postJson('/api/reports', [
        'general_information' => ['institutional_representatives' => [['name' => "\u{00A0}", 'position' => 'Secretária']]],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors([
        'general_information.institutional_representatives.0.name' => 'The general_information.institutional_representatives.0.name field is required.',
    ]);
    $this->assertDatabaseEmpty('reports');
    $this->assertDatabaseEmpty('report_series');
});

test('returns 422 for a Unicode whitespace position without modifying saved representatives', function (): void {
    $author = User::factory()->superuser()->create();
    $report = institutionalRepresentativesReport($author);
    $originalPayload = $report->payload;
    $token = institutionalRepresentativesAccessToken($author);

    $response = $this->withToken($token)->patchJson('/api/reports/'.$report->uuid, [
        'general_information' => ['institutional_representatives' => [['name' => 'Maria', 'position' => "\u{00A0}"]]],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors([
        'general_information.institutional_representatives.0.position' => 'The general_information.institutional_representatives.0.position field is required.',
    ]);
    expect($report->refresh()->payload)->toBe($originalPayload);
});
