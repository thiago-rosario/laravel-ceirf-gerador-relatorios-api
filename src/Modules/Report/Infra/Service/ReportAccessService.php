<?php

declare(strict_types=1);

namespace src\Modules\Report\Infra\Service;

use Illuminate\Support\Facades\DB;
use src\Modules\Identity\Model\User;
use src\Modules\Report\Application\DTO\ReportCreationScopeDTO;
use src\Modules\Report\Application\Exception\ReportAccessDeniedException;
use src\Modules\Report\Application\Interfaces\Service\ReportAccessServiceInterface;
use src\Modules\Report\Domain\Service\ReportAccessPolicy;
use src\Modules\Report\Model\Report;

class ReportAccessService implements ReportAccessServiceInterface
{
    public function __construct(private readonly ReportAccessPolicy $policy) {}

    public function canRead(string $userId, string $reportId): bool
    {
        $user = User::query()->where('uuid', $userId)->first();
        $report = Report::query()->where('uuid', $reportId)->first();

        if ($user === null) {
            return false;
        }

        if ($report === null) {
            return true;
        }

        $decisions = DB::table('report_reviews')->where('report_id', $report->id)->get(['status', 'reviewed_at'])
            ->map(fn (\stdClass $review): array => ['status' => $review->status === null ? null : (string) $review->status, 'reviewed' => $review->reviewed_at !== null])->all();

        return $this->policy->canRead($user->role->value, $user->id === $report->created_by,
            $user->coordination_id, $this->coordinationId($report), $this->policy->isConsultable($report->status->value, array_values($decisions)));
    }

    public function canWrite(string $userId, string $reportId): bool
    {
        $user = User::query()->where('uuid', $userId)->first();
        $report = Report::query()->where('uuid', $reportId)->first();

        if ($user === null) {
            return false;
        }

        if ($report === null) {
            return $user->role->value !== 'VIEWER';
        }

        return $this->policy->canWrite($user->role->value, $user->id === $report->created_by,
            $user->coordination_id, $this->coordinationId($report));
    }

    public function editorialReportIds(string $userId, array $reportIds): array
    {
        if ($reportIds === []) {
            return [];
        }

        $user = User::query()->where('uuid', $userId)->first();
        if ($user === null) {
            return [];
        }

        $reports = Report::query()->whereIn('uuid', $reportIds)->get(['uuid', 'created_by', 'coordination_id', 'report_type_id']);
        $typeIds = $reports->whereNull('coordination_id')->pluck('report_type_id')->filter()->unique()->all();
        $coordinationByType = DB::table('report_types')->whereIn('id', $typeIds)->pluck('coordination_id', 'id')->all();

        return array_values($reports->filter(function (Report $report) use ($user, $coordinationByType): bool {
            $coordinationId = $report->coordination_id ?? ($coordinationByType[$report->report_type_id] ?? null);

            return $this->policy->canWrite($user->role->value, $user->id === $report->created_by,
                $user->coordination_id, $coordinationId === null ? null : (int) $coordinationId);
        })->pluck('uuid')->all());
    }

    public function creationScope(string $userId, ?int $reportTypeId = null, ?int $coordinationId = null): ReportCreationScopeDTO
    {
        $user = User::query()->where('uuid', $userId)->first();

        if ($user === null) {
            throw new ReportAccessDeniedException;
        }

        if ($reportTypeId !== null) {
            $type = DB::table('report_types')->where('id', $reportTypeId)->first();

            if ($type === null || ($coordinationId !== null && $coordinationId !== (int) $type->coordination_id)) {
                throw new ReportAccessDeniedException;
            }

            $coordinationId = (int) $type->coordination_id;
        }

        $coordinationId ??= $user->coordination_id;

        if ($coordinationId !== null && ! DB::table('coordinations')->where('id', $coordinationId)->where('is_active', true)->exists()) {
            throw new ReportAccessDeniedException('A coordenação informada não está disponível para criar relatórios.');
        }

        if (! $this->policy->canCreate($user->role->value, $user->coordination_id, $coordinationId)) {
            throw new ReportAccessDeniedException('A criação de relatórios é permitida somente na sua coordenação.');
        }

        if ($reportTypeId === null && $coordinationId !== null) {
            $types = DB::table('report_types')->where('coordination_id', $coordinationId)->limit(2)->pluck('id');
            $reportTypeId = $types->count() === 1 ? (int) $types->first() : null;
        }

        return new ReportCreationScopeDTO($coordinationId, $reportTypeId);
    }

    private function coordinationId(Report $report): ?int
    {
        if ($report->coordination_id !== null) {
            return $report->coordination_id;
        }

        $id = $report->report_type_id === null ? null : DB::table('report_types')->where('id', $report->report_type_id)->value('coordination_id');

        return $id === null ? null : (int) $id;
    }
}
