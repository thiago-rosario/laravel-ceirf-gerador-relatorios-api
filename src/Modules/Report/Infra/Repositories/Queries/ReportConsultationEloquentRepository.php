<?php

declare(strict_types=1);

namespace src\Modules\Report\Infra\Repositories\Queries;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use src\Modules\Identity\Model\User;
use src\Modules\Report\Application\DTO\MunicipalityDataDTO;
use src\Modules\Report\Application\DTO\SearchReportsInputDTO;
use src\Modules\Report\Application\DTO\SearchReportsOutputDTO;
use src\Modules\Report\Application\Interfaces\Mapper\LegacyReportQueryMapperInterface;
use src\Modules\Report\Application\Interfaces\Mapper\ReportPersistenceMapperInterface;
use src\Modules\Report\Application\Interfaces\Repository\ReportConsultationRepositoryInterface;
use src\Modules\Report\Application\Interfaces\Service\ReportDataMapperServiceInterface;
use src\Modules\Report\Domain\Entity\ReportEntity;
use src\Modules\Report\Model\Report;
use stdClass;

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
        $query = $input->page === null ? $this->visibility->accessible($query, $input->actorId) : $this->visibility->consultable($query);

        if ($input->municipalityId !== null) {
            $query->where('municipality_id', $input->municipalityId);
        }

        if ($input->typology !== null && trim($input->typology) !== '') {
            $typology = trim($input->typology);
            $query->whereRaw('LOWER(TRIM(typology)) = ?', [mb_strtolower($typology)]);
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
        $pagination = null;

        if ($input->page !== null) {
            $page = $query->paginate($input->perPage, ['*'], 'page', $input->page);
            $models = $page->getCollection();
            $total = $page->total();
            $pagination = ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $total,
                'last_page' => $page->lastPage(), 'from' => $page->firstItem(), 'to' => $page->lastItem()];
        } else {
            $models = $query->get();
            $total = $models->count();
        }

        $reports = $models->map(fn (Report $model) => $this->mapper->map(ReportEntity::fromModel($model, $this->persistenceMapper, $this->legacyMapper)))->all();

        return new SearchReportsOutputDTO(array_values($reports), $total, $pagination);
    }

    public function municipalities(): array
    {
        return array_values(DB::table('municipalities')->where('state_code', 'BA')->where('is_active', true)
            ->orderBy('name')->orderBy('id')->get(['id', 'name', 'state_code'])
            ->map(fn (stdClass $municipality): MunicipalityDataDTO => new MunicipalityDataDTO((int) $municipality->id, $municipality->name, $municipality->state_code))->all());
    }
}
