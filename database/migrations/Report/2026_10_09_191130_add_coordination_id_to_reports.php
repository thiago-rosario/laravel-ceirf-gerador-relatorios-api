<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->unsignedSmallInteger('coordination_id')->nullable();
            $table->index('coordination_id', 'idx_reports_coordination');
            $table->foreign('coordination_id', 'fk_reports_coordination')->references('id')->on('coordinations')->noActionOnDelete()->noActionOnUpdate();
        });

        DB::table('reports')->chunkById(100, function (Collection $reports): void {
            foreach ($reports as $report) {
                $coordinationId = $report->report_type_id === null ? null : DB::table('report_types')->where('id', $report->report_type_id)->value('coordination_id');
                $coordinationId ??= DB::table('users')->where('id', $report->created_by)->value('coordination_id');
                $attributes = ['coordination_id' => $coordinationId];

                if ($report->payload !== null) {
                    $payload = json_decode($report->payload, true, flags: JSON_THROW_ON_ERROR);
                    $payload['coordinationId'] = $coordinationId === null ? null : (int) $coordinationId;
                    $payload['reportTypeId'] = $report->report_type_id === null ? null : (int) $report->report_type_id;
                    $attributes['payload'] = json_encode($payload, JSON_THROW_ON_ERROR);
                }

                DB::table('reports')->where('id', $report->id)->update($attributes);
            }
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropForeign(Schema::getConnection()->getDriverName() === 'sqlite' ? ['coordination_id'] : 'fk_reports_coordination');
            $table->dropIndex('idx_reports_coordination');
            $table->dropColumn('coordination_id');
        });
    }
};
