<?php

declare(strict_types=1);

namespace src\Modules\Report\Infra\Repositories\Commands;

use Illuminate\Support\Facades\DB;
use src\Modules\Report\Application\Interfaces\Mapper\LegacyReportQueryMapperInterface;
use src\Modules\Report\Application\Interfaces\Mapper\ReportPersistenceMapperInterface;
use src\Modules\Report\Application\Interfaces\Service\ReportPersistenceServiceInterface;
use src\Modules\Report\Domain\Entity\ReportEntity;
use src\Modules\Report\Domain\Enum\ReportStatusEnum;
use src\Modules\Report\Domain\Exception\InvalidReportRevisionException;
use src\Modules\Report\Domain\Exception\ReportNotGeneratedException;
use src\Modules\Report\Model\Report;

class CreateReportEloquentRepository
{
    public function __construct(
        private readonly ReportPersistenceMapperInterface $mapper,
        private readonly LegacyReportQueryMapperInterface $legacyMapper,
        private readonly ReportPersistenceServiceInterface $service,
    ) {}

    public function insert(ReportEntity $report): ReportEntity
    {
        $report->validate();

        return DB::transaction(function () use ($report): ReportEntity {
            $authorId = $this->service->authorId($report);
            $parent = null;

            if ($report->isRevision()) {
                $seriesId = DB::table('report_series')->where('uuid', $report->rootReportId()->value())->lockForUpdate()->value('id');
                $parent = Report::query()->where('uuid', $report->parentReportId()?->value())->lockForUpdate()->first();

                if ($parent === null || $seriesId === null || $parent->report_series_id !== (int) $seriesId
                    || $parent->revision_number + 1 !== $report->revisionNumber()) {
                    throw new InvalidReportRevisionException;
                }

                if ($parent->status !== ReportStatusEnum::GENERATED) {
                    throw new ReportNotGeneratedException;
                }

                $parentReport = ReportEntity::fromModel($parent, $this->mapper, $this->legacyMapper);
                $report = $this->service->preserveUploadedImages($report, $parentReport->uploadedImages());
            } else {
                $seriesId = DB::table('report_series')->insertGetId([
                    'uuid' => $report->rootReportId()->value(),
                    'created_by' => $authorId,
                    'created_at' => $report->createdAt(),
                    'updated_at' => $report->updatedAt(),
                ]);
            }

            $model = Report::query()->create([
                ...$this->service->attributes($report),
                'created_by' => $authorId,
                'report_series_id' => $seriesId,
                'previous_report_id' => $parent?->id,
                'report_type_id' => $parent === null ? $report->reportTypeId() : $parent->report_type_id,
                'coordination_id' => $parent === null ? $report->coordinationId() : $parent->coordination_id,
            ]);
            $this->service->storeMedia($report, $model->id, $authorId, $parent?->id);

            return ReportEntity::fromModel($model, $this->mapper, $this->legacyMapper);
        });
    }
}
