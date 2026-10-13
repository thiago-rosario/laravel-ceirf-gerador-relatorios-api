<?php

declare(strict_types=1);

namespace src\Modules\Report\Infra\Repositories\Queries;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use src\Modules\Identity\Model\User;
use src\Modules\Report\Application\DTO\SearchReportsInputDTO;
use src\Modules\Report\Application\DTO\SearchReportsOutputDTO;
use src\Modules\Report\Application\Interfaces\Mapper\LegacyReportQueryMapperInterface;
use src\Modules\Report\Application\Interfaces\Mapper\ReportPersistenceMapperInterface;
use src\Modules\Report\Application\Interfaces\Repository\ReportConsultationRepositoryInterface;
use src\Modules\Report\Application\Interfaces\Service\ReportDataMapperServiceInterface;
use src\Modules\Report\Domain\Entity\ReportEntity;
use src\Modules\Report\Model\Report;

class ReportConsultationEloquentRepository implements ReportConsultationRepositoryInterface
{
    public function __construct(
        private readonly ReportVisibilityQuery $visibility,
        private readonly ReportPersistenceMapperInterface $persistenceMapper,
        private readonly LegacyReportQueryMapperInterface $legacyMapper,
        private readonly ReportDataMapperServiceInterface $mapper,
    ) {}

    public function search(SearchReportsInputDTO $input): SearchReportsOutputDTO
    {
        $query = Report::query();
        $query = $this->visibility->accessible($query, $input->actorId);

        if ($input->municipalityId !== null) {
            $query->where('municipality_id', $input->municipalityId);
        }

        if ($input->userId !== null) {
            $query->whereIn('created_by', User::query()->select('id')->where('uuid', $input->userId));
        }

        if ($input->coordinationId !== null) {
            $this->visibility->forCoordination($query, $input->coordinationId);
        }

        if ($input->rootReportId !== null) {
            $query->whereIn('report_series_id', DB::table('report_series')->select('id')->where('uuid', $input->rootReportId));
        }

        if ($input->month !== null) {
            $start = new DateTimeImmutable($input->month.'-01');
            $query->where('created_at', '>=', $start->format('Y-m-d H:i:s'))
                ->where('created_at', '<', $start->modify('+1 month')->format('Y-m-d H:i:s'));
        }

        if ($input->countOnly) {
            return new SearchReportsOutputDTO([], $query->count());
        }

        $input->rootReportId === null ? $query->orderByDesc('created_at')->orderByDesc('id') : $query->orderBy('revision_number')->orderBy('id');
        $models = $query->get();
        $total = $models->count();

        $reports = $models->map(fn (Report $model) => $this->mapper->map(ReportEntity::fromModel($model, $this->persistenceMapper, $this->legacyMapper)))->all();

        return new SearchReportsOutputDTO(array_values($reports), $total);
    }

}
