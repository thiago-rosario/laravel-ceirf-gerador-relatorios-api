<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use src\Modules\Identity\Model\User;
use src\Modules\Report\Model\Report;

/** @return array{coordination_id: int, report_type_id: int} */
function reportConsultationCoordination(string $code): array
{
    $coordinationId = DB::table('coordinations')->insertGetId(['code' => $code, 'name' => $code]);
    $reportTypeId = DB::table('report_types')->insertGetId([
        'coordination_id' => $coordinationId, 'code' => 'inspection', 'name' => 'Vistorias de Terreno',
    ]);

    return ['coordination_id' => $coordinationId, 'report_type_id' => $reportTypeId];
}


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
