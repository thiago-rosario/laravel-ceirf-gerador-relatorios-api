<?php

declare(strict_types=1);

use Database\Seeders\Force\ForceSeeder;
use Database\Seeders\Sizes\SizeSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Mockery\MockInterface;
use src\Modules\Identity\Domain\Enum\UserRoleEnum;
use src\Modules\Identity\Model\User;
use src\Modules\Report\Application\Catalog\DTO\ListReportAnswersInputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportAnswersOutputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportChecklistInputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportChecklistOutputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportForcesInputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportForcesOutputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportInfrastructureInputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportInfrastructureOutputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportTypologiesInputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportTypologiesOutputDTO;
use src\Modules\Report\Application\Catalog\Interfaces\Repository\ReportCatalogRepositoryInterface;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportAnswersUsecaseInterface;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportChecklistUsecaseInterface;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportForcesUsecaseInterface;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportInfrastructureUsecaseInterface;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportTypologiesUsecaseInterface;
use src\Modules\Report\Model\Report;
use src\Modules\Report\Presentation\Catalog\Exception\ForceNotFoundException;
use src\Modules\Report\Presentation\Catalog\Exception\ListReportAnswersException;
use src\Modules\Report\Presentation\Catalog\Exception\ListReportChecklistException;
use src\Modules\Report\Presentation\Catalog\Exception\ListReportForcesException;
use src\Modules\Report\Presentation\Catalog\Exception\ListReportInfrastructureException;
use src\Modules\Report\Presentation\Catalog\Exception\ListReportTypologiesException;

use function Pest\Laravel\mock;

function reportCatalogAccessToken(User $user): string
{
    $response = test()->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password']);
    $response->assertOk();
    Auth::forgetGuards();

    return $response->json('data.access_token');
}

dataset('report catalog endpoints', [
    'forces' => ['/api/reports/catalogs/forces'],
    'typologies' => ['/api/reports/catalogs/typologies'],
    'answers' => ['/api/reports/catalogs/answers'],
    'infrastructure' => ['/api/reports/catalogs/infrastructure'],
    'checklist' => ['/api/reports/catalogs/checklist'],
]);

test('catalog controllers execute their injected usecase contracts', function (string $catalog, string $contract, string $inputClass, string $outputClass): void {
    $user = User::factory()->create();
    $token = reportCatalogAccessToken($user);
    $options = [['key' => 'contract-option', 'label' => 'Option supplied by the usecase contract']];
    $items = [['code' => 'contract-option', 'name' => 'Option supplied by the usecase contract']];
    if ($catalog === 'forces') {
        $options[0]['label'] = 'contract-option';
        $options[0]['name'] = 'Name supplied by the usecase contract';
        $items = [['id' => 1, 'code' => 'contract-option', 'name' => 'Name supplied by the usecase contract']];
    } elseif ($catalog === 'typologies') {
        $items[0]['id'] = 1;
    }
    $result = new $outputClass(items: $items);
    mock($contract, function (MockInterface $mock) use ($result, $inputClass): void {
        $mock->shouldReceive('__invoke')->once()->with(Mockery::type($inputClass))->andReturn($result);
    });

    $response = $this->withToken($token)->getJson('/api/reports/catalogs/'.$catalog);

    $response->assertOk()->assertExactJson(['status' => 'success', 'data' => [$catalog => $options]]);
})->with([
    'forces' => ['forces', ListReportForcesUsecaseInterface::class, ListReportForcesInputDTO::class, ListReportForcesOutputDTO::class],
    'typologies' => ['typologies', ListReportTypologiesUsecaseInterface::class, ListReportTypologiesInputDTO::class, ListReportTypologiesOutputDTO::class],
    'answers' => ['answers', ListReportAnswersUsecaseInterface::class, ListReportAnswersInputDTO::class, ListReportAnswersOutputDTO::class],
    'infrastructure' => ['infrastructure', ListReportInfrastructureUsecaseInterface::class, ListReportInfrastructureInputDTO::class, ListReportInfrastructureOutputDTO::class],
    'checklist' => ['checklist', ListReportChecklistUsecaseInterface::class, ListReportChecklistInputDTO::class, ListReportChecklistOutputDTO::class],
]);

test('returns 401 when a report catalog is requested without authentication', function (string $uri): void {
    $this->getJson($uri)->assertUnauthorized()->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Não autenticado.');
})->with('report catalog endpoints');

test('returns 403 for report catalogs when a password change is required', function (string $uri): void {
    $user = User::factory()->mustChangePassword()->create();
    $token = reportCatalogAccessToken($user);

    $this->withToken($token)->getJson($uri)->assertForbidden()->assertJsonPath('data.must_change_password', true);
})->with('report catalog endpoints');

test('returns each isolated catalog to every authenticated report profile', function (UserRoleEnum $role, string $uri): void {
    $this->seed([ForceSeeder::class, SizeSeeder::class]);
    $user = User::factory()->withRole($role)->create();
    $token = reportCatalogAccessToken($user);

    $catalog = basename($uri);
    $counts = ['forces' => 6, 'typologies' => 11, 'answers' => 3, 'infrastructure' => 10, 'checklist' => 20];

    $response = $this->withToken($token)->getJson($uri);

    $response->assertOk()->assertJsonPath('status', 'success')->assertJsonCount(1, 'data')
        ->assertJsonCount($counts[$catalog], 'data.'.$catalog);
})->with([
    'operator' => [UserRoleEnum::OPERATOR], 'reviewer' => [UserRoleEnum::REVIEWER],
    'viewer' => [UserRoleEnum::VIEWER], 'superuser' => [UserRoleEnum::SUPERUSER],
])->with('report catalog endpoints');

test('preserves all public identifiers and their original order in the individual catalogs', function (): void {
    $this->seed([ForceSeeder::class, SizeSeeder::class]);
    $user = User::factory()->create();
    $token = reportCatalogAccessToken($user);
    $expectedKeys = [
        'forces' => ['PM', 'PC', 'CBM', 'DPT', 'PC-PM', 'SSP'],
        'typologies' => ['1B', '1A', '1', '1B-1B', '1B-1A', '1B-1', '1A-1A', '1A-A', '1-1', '1A-1B', 'SEM PADRÃO'],
        'answers' => ['SIM', 'NÃO', 'NÃO SE APLICA'],
        'infrastructure' => [
            'water_network', 'high_voltage_network', 'low_voltage_network', 'sewage_network', 'telephony',
            'public_lighting', 'internet', 'waste_collection', 'paving', 'existing_buildings',
        ],
        'checklist' => [
            'sei_construction_request', 'sei_land_and_typology_identification', 'state_owned_land', 'simov_legalized',
            'compatible_dimensions', 'slope_or_level_risk', 'stormwater_drainage', 'flood_history', 'electricity_supply',
            'water_supply', 'sewage_supply', 'paving_and_sidewalk', 'regular_waste_collection', 'demolition_required',
            'easy_public_access', 'domain_strip_or_non_buildable_area', 'technical_feasibility_report',
            'report_attached_to_sei', 'works_dashboard_updated', 'environmental_protection_area',
        ],
    ];

    $options = [];

    foreach ($expectedKeys as $catalog => $keys) {
        $response = $this->withToken($token)->getJson('/api/reports/catalogs/'.$catalog);
        $options[$catalog] = $response->assertOk()->json('data.'.$catalog);
        $response->assertExactJson(['status' => 'success', 'data' => [$catalog => $options[$catalog]]]);
        expect(array_column($options[$catalog], 'key'))->toBe($keys);
        foreach ($options[$catalog] as $option) {
            expect(array_keys($option))->toBe($catalog === 'forces' ? ['key', 'label', 'name'] : ['key', 'label']);
            expect($option['label'])->toBeString()->not->toBeEmpty();
        }
    }
    expect($options['infrastructure'][0]['label'])->toBe('Rede de Água');
    expect($options['checklist'][0]['label'])->toBe('Solicitação via Sistema Eletrônico de Informação – SEI para construção da unidade.');
    expect($options['checklist'][19]['label'])->toBe('Verificar se o terreno se encontra em área de preservação ambiental.');
});

test('registers only the five explicit catalog routes without an aggregated or generic catalog endpoint', function (): void {
    expect(Route::has('reports.form-options'))->toBeFalse();
    expect(Route::has('reports.catalogs.list'))->toBeFalse();
});

test('retrieves stored force records without applying the public catalog rules in the repository', function (): void {
    DB::table('forces')->insert([
        ['id' => 20, 'code' => 'BM', 'name' => 'Bombeiro cadastrado', 'is_active' => false],
        ['id' => 10, 'code' => 'UNKNOWN', 'name' => 'Outra instituição', 'is_active' => true],
    ]);

    $records = $this->app->make(ReportCatalogRepositoryInterface::class)->forces();

    expect(array_map(fn (stdClass $force): array => (array) $force, $records))->toBe([
        ['id' => 10, 'code' => 'UNKNOWN', 'name' => 'Outra instituição'],
        ['id' => 20, 'code' => 'BM', 'name' => 'Bombeiro cadastrado'],
    ]);
});

test('retrieves stored typologies without normalizing or selecting public options in the repository', function (): void {
    DB::table('sizes')->insert([
        ['id' => 20, 'name' => 'sem padrão'],
        ['id' => 10, 'name' => 'desconhecido'],
    ]);

    $records = $this->app->make(ReportCatalogRepositoryInterface::class)->typologies();

    expect(array_map(fn (stdClass $typology): array => (array) $typology, $records))->toBe([
        ['id' => 10, 'name' => 'desconhecido'],
        ['id' => 20, 'name' => 'sem padrão'],
    ]);
});

test('lists only stored supported forces and sizes independently of insertion order and preserves inactive legacy forces', function (): void {
    DB::table('forces')->insert([
        ['code' => 'SSP', 'name' => 'Secretaria', 'is_active' => true],
        ['code' => 'UNKNOWN', 'name' => 'Outro', 'is_active' => true],
        ['code' => 'BM', 'name' => 'Bombeiro', 'is_active' => false],
        ['code' => 'PM', 'name' => 'Militar', 'is_active' => true],
    ]);
    DB::table('sizes')->insert([['name' => 'sem padrão'], ['name' => 'desconhecido'], ['name' => '1A']]);
    $user = User::factory()->create();
    $token = reportCatalogAccessToken($user);

    $this->withToken($token)->getJson('/api/reports/catalogs/forces')->assertOk()
        ->assertExactJson(['status' => 'success', 'data' => ['forces' => [
            ['key' => 'PM', 'label' => 'PM', 'name' => 'Militar'],
            ['key' => 'CBM', 'label' => 'CBM', 'name' => 'Bombeiro'],
            ['key' => 'SSP', 'label' => 'SSP', 'name' => 'Secretaria'],
        ]]]);
    $this->withToken($token)->getJson('/api/reports/catalogs/typologies')->assertOk()
        ->assertExactJson(['status' => 'success', 'data' => ['typologies' => [
            ['key' => '1A', 'label' => '1A'], ['key' => 'SEM PADRÃO', 'label' => 'SEM PADRÃO'],
        ]]]);
});

test('returns a single public force option with its stored name when database aliases are available', function (array $forces, string $expectedName): void {
    DB::table('forces')->insert($forces);
    $user = User::factory()->create();
    $token = reportCatalogAccessToken($user);

    $response = $this->withToken($token)->getJson('/api/reports/catalogs/forces');

    $response->assertOk()->assertExactJson(['status' => 'success', 'data' => ['forces' => [
        ['key' => 'CBM', 'label' => 'CBM', 'name' => $expectedName],
    ]]]);
})->with([
    'public code without the database alias' => [[['code' => 'CBM', 'name' => 'Nome cadastrado no código público']], 'Nome cadastrado no código público'],
    'database alias inserted after the public code' => [[
        ['code' => 'CBM', 'name' => 'Nome do código público'],
        ['code' => 'BM', 'name' => 'Nome do código de persistência'],
    ], 'Nome do código de persistência'],
    'database alias inserted before the public code' => [[
        ['code' => 'BM', 'name' => 'Nome do código de persistência'],
        ['code' => 'CBM', 'name' => 'Nome do código público'],
    ], 'Nome do código de persistência'],
]);

test('preserves each database catalog response when the reference tables are empty', function (): void {
    $user = User::factory()->create();
    $token = reportCatalogAccessToken($user);

    $this->withToken($token)->getJson('/api/reports/catalogs/forces')->assertBadRequest()
        ->assertExactJson(['status' => 'error', 'data' => [], 'message' => 'Força não encontrada !', 'code' => 2302]);
    $this->withToken($token)->getJson('/api/reports/catalogs/typologies')->assertOk()
        ->assertExactJson(['status' => 'success', 'data' => ['typologies' => []]]);
});

test('returns 404 for an unknown report catalog', function (): void {
    $user = User::factory()->create();
    $token = reportCatalogAccessToken($user);

    $this->withToken($token)->getJson('/api/reports/catalogs/unknown')->assertNotFound();
});

test('returns 400 with the catalog exception message and code when listing fails', function (string $catalog, string $contract, string $inputClass, string $exceptionClass, string $expectedMessage, int $expectedCode): void {
    $user = User::factory()->create();
    $token = reportCatalogAccessToken($user);
    mock($contract, function (MockInterface $mock) use ($exceptionClass, $inputClass): void {
        $mock->shouldReceive('__invoke')->once()->with(Mockery::type($inputClass))
            ->andThrow(new $exceptionClass);
    });

    $response = $this->withToken($token)->getJson('/api/reports/catalogs/'.$catalog);

    $response->assertBadRequest()->assertExactJson([
        'status' => 'error', 'data' => [], 'message' => $expectedMessage, 'code' => $expectedCode,
    ]);
})->with([
    'force listing exception' => ['forces', ListReportForcesUsecaseInterface::class, ListReportForcesInputDTO::class, ListReportForcesException::class, 'Erro ao listar as forças!', 2301],
    'force not found exception' => ['forces', ListReportForcesUsecaseInterface::class, ListReportForcesInputDTO::class, ForceNotFoundException::class, 'Força não encontrada !', 2302],
    'answer listing exception' => ['answers', ListReportAnswersUsecaseInterface::class, ListReportAnswersInputDTO::class, ListReportAnswersException::class, 'Erro ao listar as respostas!', 2303],
    'checklist listing exception' => ['checklist', ListReportChecklistUsecaseInterface::class, ListReportChecklistInputDTO::class, ListReportChecklistException::class, 'Erro ao listar o checklist!', 2304],
    'infrastructure listing exception' => ['infrastructure', ListReportInfrastructureUsecaseInterface::class, ListReportInfrastructureInputDTO::class, ListReportInfrastructureException::class, 'Erro ao listar a infraestrutura!', 2305],
    'typology listing exception' => ['typologies', ListReportTypologiesUsecaseInterface::class, ListReportTypologiesInputDTO::class, ListReportTypologiesException::class, 'Erro ao listar as tipologias!', 2306],
]);

test('reports catalog failures using the existing generic 500 response', function (string $uri, string $method): void {
    $user = User::factory()->create();
    $token = reportCatalogAccessToken($user);
    Exceptions::fake();
    mock(ReportCatalogRepositoryInterface::class, function (MockInterface $mock) use ($method): void {
        $mock->shouldReceive($method)->once()->andThrow(new RuntimeException('Private database details'));
    });

    $response = $this->withToken($token)->getJson($uri);

    $response->assertInternalServerError()->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'An unexpected error occurred');
    Exceptions::assertReported(RuntimeException::class);
})->with([
    'forces' => ['/api/reports/catalogs/forces', 'forces'],
    'typologies' => ['/api/reports/catalogs/typologies', 'typologies'],
]);

test('reports static catalog failures using the generic 500 response', function (string $catalog, string $contract): void {
    $user = User::factory()->create();
    $token = reportCatalogAccessToken($user);
    Exceptions::fake();
    mock($contract, function (MockInterface $mock): void {
        $mock->shouldReceive('__invoke')->once()->andThrow(new RuntimeException('Private catalog details'));
    });

    $response = $this->withToken($token)->getJson('/api/reports/catalogs/'.$catalog);

    $response->assertInternalServerError()->assertExactJson([
        'status' => 'error', 'data' => [], 'message' => 'An unexpected error occurred', 'code' => 500,
    ]);
    Exceptions::assertReported(RuntimeException::class);
})->with([
    'answers' => ['answers', ListReportAnswersUsecaseInterface::class],
    'infrastructure' => ['infrastructure', ListReportInfrastructureUsecaseInterface::class],
    'checklist' => ['checklist', ListReportChecklistUsecaseInterface::class],
]);

test('creates updates and retrieves report values supplied by the catalogs while preserving relational aliases', function (): void {
    $this->seed([ForceSeeder::class, SizeSeeder::class]);
    $sizeId = DB::table('sizes')->where('name', 'SEM PADRÃO')->value('id');
    expect($sizeId)->toBeInt();
    $author = User::factory()->superuser()->create();
    $token = reportCatalogAccessToken($author);
    $infrastructureOptions = $this->withToken($token)->getJson('/api/reports/catalogs/infrastructure')->assertOk()->json('data.infrastructure');
    $checklistOptions = $this->withToken($token)->getJson('/api/reports/catalogs/checklist')->assertOk()->json('data.checklist');
    $infrastructure = array_fill_keys(array_column($infrastructureOptions, 'key'), 'SIM');
    $checklist = array_fill_keys(array_column($checklistOptions, 'key'), 'NÃO SE APLICA');

    $created = $this->withToken($token)->postJson('/api/reports', [
        'cover' => ['force' => 'CBM', 'size' => 'SEM PADRÃO', 'typology' => 'Texto livre existente'],
        'infrastructure' => $infrastructure, 'attachments' => ['checklist' => $checklist],
    ])->assertCreated();

    $reportId = $created->json('data.id');
    $this->assertDatabaseHas('reports', [
        'uuid' => $reportId,
        'force_id' => DB::table('forces')->where('code', 'BM')->value('id'),
        'size_id' => $sizeId,
        'infrastructure_water_network' => 1, 'checklist_sei_construction_request' => 2,
    ]);
    $this->withToken($token)->getJson('/api/reports/'.$reportId)->assertOk()
        ->assertJsonPath('data.cover.force', 'CBM')->assertJsonPath('data.cover.size', 'SEM PADRÃO')
        ->assertJsonPath('data.infrastructure', $infrastructure)->assertJsonPath('data.attachments.checklist', $checklist);

    $this->withToken($token)->patchJson('/api/reports/'.$reportId, [
        'cover' => ['force' => 'PC-PM', 'size' => '1A-1B', 'typology' => 'Texto livre existente'],
        'infrastructure' => array_replace($infrastructure, ['water_network' => 'NÃO']),
        'attachments' => ['checklist' => array_replace($checklist, ['sei_construction_request' => 'SIM'])],
    ])->assertOk()->assertJsonPath('data.cover.force', 'PC-PM')->assertJsonPath('data.cover.size', '1A-1B');

    $this->withToken($token)->getJson('/api/reports/'.$reportId)->assertOk()
        ->assertJsonPath('data.cover.typology', 'Texto livre existente')
        ->assertJsonPath('data.infrastructure.water_network', 'NÃO')
        ->assertJsonPath('data.attachments.checklist.sei_construction_request', 'SIM');
    $this->assertDatabaseHas('reports', [
        'uuid' => $reportId, 'infrastructure_water_network' => 0, 'checklist_sei_construction_request' => 1,
        'force_id' => DB::table('forces')->where('code', 'PC-PM')->value('id'),
        'size_id' => DB::table('sizes')->where('name', '1A-1B')->value('id'),
    ]);
});

test('retrieves and updates legacy relational reports without changing their force and size identifiers', function (): void {
    $forceId = DB::table('forces')->insertGetId(['code' => 'BM', 'name' => 'Bombeiro', 'is_active' => false]);
    $sizeId = DB::table('sizes')->insertGetId(['name' => 'Sem Padrão']);
    $author = User::factory()->superuser()->create();
    $report = Report::factory()->create([
        'created_by' => $author->id, 'payload' => null, 'force_id' => $forceId, 'size_id' => $sizeId,
        'infrastructure_water_network' => 0, 'checklist_sei_construction_request' => 2,
    ]);
    $token = reportCatalogAccessToken($author);

    $this->withToken($token)->getJson('/api/reports/'.$report->uuid)->assertOk()
        ->assertJsonPath('data.cover.force', 'CBM')->assertJsonPath('data.cover.size', 'SEM PADRÃO')
        ->assertJsonPath('data.infrastructure.water_network', 'NÃO')
        ->assertJsonPath('data.attachments.checklist.sei_construction_request', 'NÃO SE APLICA');

    $this->withToken($token)->patchJson('/api/reports/'.$report->uuid, ['conclusion' => ['content' => 'Revisado.']])
        ->assertOk()->assertJsonPath('data.cover.force', 'CBM')->assertJsonPath('data.cover.size', 'SEM PADRÃO');
    $this->assertDatabaseHas('reports', ['uuid' => $report->uuid, 'force_id' => $forceId, 'size_id' => $sizeId]);
});

test('preserves existing foreign keys when equivalent database codes coexist and only another section changes', function (): void {
    DB::table('forces')->insert(['code' => 'BM', 'name' => 'Bombeiro legado']);
    $forceId = DB::table('forces')->insertGetId(['code' => 'CBM', 'name' => 'Bombeiro cadastrado']);
    DB::table('sizes')->insert(['name' => 'Sem Padrão']);
    $sizeId = DB::table('sizes')->insertGetId(['name' => 'SEM PADRÃO']);
    $author = User::factory()->superuser()->create();
    $report = Report::factory()->create([
        'created_by' => $author->id, 'payload' => null, 'force_id' => $forceId, 'size_id' => $sizeId,
    ]);
    $token = reportCatalogAccessToken($author);

    $this->withToken($token)->patchJson('/api/reports/'.$report->uuid, ['conclusion' => ['content' => 'Revisado.']])
        ->assertOk()->assertJsonPath('data.cover.force', 'CBM')->assertJsonPath('data.cover.size', 'SEM PADRÃO');

    $this->assertDatabaseHas('reports', ['uuid' => $report->uuid, 'force_id' => $forceId, 'size_id' => $sizeId]);
    $this->withToken($token)->getJson('/api/reports/'.$report->uuid)->assertOk()
        ->assertJsonPath('data.cover.force', 'CBM')->assertJsonPath('data.cover.size', 'SEM PADRÃO');
});

test('returns 422 without changing reports when a catalog value or field is invalid', function (string $method, array $payload, string $field): void {
    $this->seed([ForceSeeder::class, SizeSeeder::class]);
    $author = User::factory()->superuser()->create();
    $report = Report::factory()->create(['created_by' => $author->id]);
    $original = $report->refresh()->getAttributes();
    $token = reportCatalogAccessToken($author);
    $uri = '/api/reports'.($method === 'PATCH' ? '/'.$report->uuid : '');

    $response = $this->withToken($token)->json($method, $uri, $payload);

    $response->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('reports', 1);
    expect($report->refresh()->getAttributes())->toBe($original);
})->with(['creation' => ['POST'], 'update' => ['PATCH']])->with([
    'unknown force' => [['cover' => ['force' => 'INVALID']], 'cover.force'],
    'database alias is not a public force code' => [['cover' => ['force' => 'BM']], 'cover.force'],
    'unknown size' => [['cover' => ['size' => 'INVALID']], 'cover.size'],
    'invalid infrastructure answer' => [['infrastructure' => ['water_network' => 'YES']], 'infrastructure.water_network'],
    'invalid checklist answer' => [['attachments' => ['checklist' => ['sei_construction_request' => 'YES']]], 'attachments.checklist.sei_construction_request'],
    'unknown infrastructure key' => [['infrastructure' => ['unknown' => 'SIM']], 'infrastructure'],
    'unknown checklist key' => [['attachments' => ['checklist' => ['unknown' => 'SIM']]], 'attachments.checklist'],
]);

test('returns 422 for enum codes missing from their reference tables', function (array $payload, string $field): void {
    $author = User::factory()->superuser()->create();
    $token = reportCatalogAccessToken($author);

    $response = $this->withToken($token)->postJson('/api/reports', $payload);

    $response->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseEmpty('reports');
})->with([
    'missing force record' => [['cover' => ['force' => 'PM']], 'cover.force'],
    'missing size record' => [['cover' => ['size' => '1B']], 'cover.size'],
]);
