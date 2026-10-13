<?php

declare(strict_types=1);

namespace src\Modules\Report\Infra\Mapper;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use src\Modules\Report\Application\Interfaces\Mapper\LegacyReportQueryMapperInterface;
use src\Modules\Report\Domain\Entity\ReportEntity;
use src\Modules\Report\Domain\Enum\ReportStatusEnum;
use src\Modules\Report\Domain\ValueObject\ReportConclusionValueObject;
use src\Modules\Report\Domain\ValueObject\ReportGeneralInformationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportLocationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPhotographicDocumentationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPreImplementationValueObject;
use src\Modules\Report\Infra\Trait\MapsLegacyReportMediaTrait;
use src\Modules\Report\Infra\Trait\MapsLegacyReportSectionsTrait;
use src\Modules\Report\Model\Report as ReportModel;
use UnexpectedValueException;

class LegacyReportQueryMapper implements LegacyReportQueryMapperInterface
{
    use MapsLegacyReportMediaTrait;
    use MapsLegacyReportSectionsTrait;

    public function fromModel(ReportModel $model): ReportEntity
    {
        if ($model->status !== ReportStatusEnum::DRAFT) {
            throw new UnexpectedValueException('O relatório gerado não possui um snapshot de conteúdo.');
        }

        $row = DB::table('reports')
            ->join('users', 'users.id', '=', 'reports.created_by')
            ->join('report_series', 'report_series.id', '=', 'reports.report_series_id')
            ->leftJoin('reports as previous_report', 'previous_report.id', '=', 'reports.previous_report_id')
            ->leftJoin('municipalities', 'municipalities.id', '=', 'reports.municipality_id')
            ->leftJoin('forces', 'forces.id', '=', 'reports.force_id')
            ->leftJoin('sizes', 'sizes.id', '=', 'reports.size_id')
            ->where('reports.id', $model->id)
            ->first([
                'reports.*',
                'users.uuid as author_uuid',
                'report_series.uuid as root_uuid',
                'previous_report.uuid as parent_uuid',
                'municipalities.name as municipality_name',
                'municipalities.state_code as municipality_state_code',
                'forces.code as force_code',
                'sizes.name as size_name',
            ]);

        if ($row === null) {
            throw new UnexpectedValueException('As referências do relatório legado não puderam ser restauradas.');
        }

        $images = $this->images($model->id);
        $checklist = $this->checklist($row);

        return new ReportEntity(
            id: (string) $row->uuid,
            rootReportId: (string) $row->root_uuid,
            parentReportId: $row->parent_uuid === null ? null : (string) $row->parent_uuid,
            createdBy: (string) $row->author_uuid,
            revisionNumber: (int) $row->revision_number,
            status: ReportStatusEnum::DRAFT,
            cover: $this->cover($row),
            generalInformation: $row->inspection_date === null && $row->present_collaborators === null ? null : new ReportGeneralInformationValueObject(
                inspectionDate: $row->inspection_date === null ? null : new DateTimeImmutable((string) $row->inspection_date),
                collaborators: (string) ($row->present_collaborators ?? ''),
            ),
            infrastructure: $this->infrastructure($row),
            location: $images['locationMap'] === null && $images['municipalityInStateMap'] === null ? null : new ReportLocationValueObject(
                locationMap: $images['locationMap'],
                municipalityInStateMap: $images['municipalityInStateMap'],
            ),
            preImplementation: $images['preImplementation'] === null ? null : new ReportPreImplementationValueObject($images['preImplementation']),
            photographicDocumentation: $images['photographs'] === [] ? null : new ReportPhotographicDocumentationValueObject($images['photographs']),
            attachments: $this->attachments($model->id, $checklist),
            conclusion: $row->conclusion === null ? null : new ReportConclusionValueObject((string) $row->conclusion),
            uploadedImages: $images['uploadedImages'],
            createdAt: $model->created_at,
            updatedAt: $model->updated_at,
            coordinationId: $model->coordination_id,
            reportTypeId: $model->report_type_id,
        );
    }
}
