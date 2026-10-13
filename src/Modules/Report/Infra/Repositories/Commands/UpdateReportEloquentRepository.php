<?php

declare(strict_types=1);

namespace src\Modules\Report\Infra\Repositories\Commands;

use Illuminate\Support\Facades\DB;
use src\Modules\Report\Application\Exception\ReportNotFoundException;
use src\Modules\Report\Application\Interfaces\Mapper\LegacyReportQueryMapperInterface;
use src\Modules\Report\Application\Interfaces\Mapper\ReportPersistenceMapperInterface;
use src\Modules\Report\Application\Interfaces\Service\ReportPersistenceServiceInterface;
use src\Modules\Report\Domain\Entity\ReportEntity;
use src\Modules\Report\Domain\Enum\ReportStatusEnum;
use src\Modules\Report\Domain\Exception\InvalidReportRevisionException;
use src\Modules\Report\Domain\Exception\ReportAlreadyGeneratedException;
use src\Modules\Report\Model\Report;

class UpdateReportEloquentRepository
{
    public function __construct(
        private readonly ReportPersistenceMapperInterface $mapper,
        private readonly LegacyReportQueryMapperInterface $legacyMapper,
        private readonly ReportPersistenceServiceInterface $service,
    ) {}

    public function update(ReportEntity $report): ReportEntity
    {
        $report->validate();

        return DB::transaction(function () use ($report): ReportEntity {
            $model = Report::query()->where('uuid', $report->id()->value())->lockForUpdate()->first();

            if ($model === null) {
                throw new ReportNotFoundException;
            }

            if ($model->status === ReportStatusEnum::GENERATED) {
                throw new ReportAlreadyGeneratedException;
            }

            $previousReport = ReportEntity::fromModel($model, $this->mapper, $this->legacyMapper);

            if ($report->rootReportId()->value() !== $previousReport->rootReportId()->value()
                || $report->parentReportId()?->value() !== $previousReport->parentReportId()?->value()
                || $report->revisionNumber() !== $previousReport->revisionNumber()
                || $report->createdBy()->value() !== $previousReport->createdBy()->value()
                || $report->coordinationId() !== $previousReport->coordinationId()
                || $report->reportTypeId() !== $previousReport->reportTypeId()
                || $report->createdAt() != $previousReport->createdAt()) {
                throw new InvalidReportRevisionException;
            }

            $reportToStore = $this->service->preserveUploadedImages($report, $previousReport->uploadedImages());
            $model->fill($this->service->attributes($reportToStore));
            $dirty = $model->getDirty();

            if ($dirty !== [] && Report::query()->whereKey($model->id)->where('status', ReportStatusEnum::DRAFT->value)->update($dirty) !== 1) {
                throw new ReportAlreadyGeneratedException;
            }

            $this->service->storeMedia($reportToStore, $model->id, $model->created_by);

            return $reportToStore;
        });
    }
}
