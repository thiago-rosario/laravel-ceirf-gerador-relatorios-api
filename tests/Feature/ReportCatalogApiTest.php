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
use src\Modules\Report\Application\Catalog\Interfaces\Repository\ReportCatalogRepositoryInterface;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportAnswersUsecaseInterface;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportChecklistUsecaseInterface;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportForcesUsecaseInterface;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportInfrastructureUsecaseInterface;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportTypologiesUsecaseInterface;
use src\Modules\Report\Model\Report;

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

test('catalog controllers execute their injected usecase contracts', function (string $catalog, string $contract): void {
    $user = User::factory()->create();
    $token = reportCatalogAccessToken($user);
    $options = [['key' => 'contract-option', 'label' => 'Option supplied by the usecase contract']];
    mock($contract, function (MockInterface $mock) use ($options): void {
        $mock->shouldReceive('__invoke')->once()->andReturn($options);
    });

    $response = $this->withToken($token)->getJson('/api/reports/catalogs/'.$catalog);

    $response->assertOk()->assertExactJson(['status' => 'success', 'data' => [$catalog => $options]]);
})->with([
    'forces' => ['forces', ListReportForcesUsecaseInterface::class],
    'typologies' => ['typologies', ListReportTypologiesUsecaseInterface::class],
    'answers' => ['answers', ListReportAnswersUsecaseInterface::class],
    'infrastructure' => ['infrastructure', ListReportInfrastructureUsecaseInterface::class],
    'checklist' => ['checklist', ListReportChecklistUsecaseInterface::class],
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
            expect(array_keys($option))->toBe(['key', 'label']);
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
            ['key' => 'PM', 'label' => 'PM'], ['key' => 'CBM', 'label' => 'CBM'], ['key' => 'SSP', 'label' => 'SSP'],
        ]]]);
    $this->withToken($token)->getJson('/api/reports/catalogs/typologies')->assertOk()
        ->assertExactJson(['status' => 'success', 'data' => ['typologies' => [
            ['key' => '1A', 'label' => '1A'], ['key' => 'SEM PADRÃO', 'label' => 'SEM PADRÃO'],
        ]]]);
});

test('does not fabricate database options when the reference tables are empty', function (): void {
    $user = User::factory()->create();
    $token = reportCatalogAccessToken($user);

    $this->withToken($token)->getJson('/api/reports/catalogs/forces')->assertOk()
        ->assertExactJson(['status' => 'success', 'data' => ['forces' => []]]);
    $this->withToken($token)->getJson('/api/reports/catalogs/typologies')->assertOk()
        ->assertExactJson(['status' => 'success', 'data' => ['typologies' => []]]);
});

test('returns 404 for an unknown report catalog', function (): void {
    $user = User::factory()->create();
    $token = reportCatalogAccessToken($user);

    $this->withToken($token)->getJson('/api/reports/catalogs/unknown')->assertNotFound();
});

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
