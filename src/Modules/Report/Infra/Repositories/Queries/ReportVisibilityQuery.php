<?php

declare(strict_types=1);

namespace src\Modules\Report\Infra\Repositories\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use src\Modules\Identity\Model\User;
use src\Modules\Report\Model\Report;

class ReportVisibilityQuery
{
    /**
     * @param  Builder<Report>  $query
     * @return Builder<Report>
     */
    public function consultable(Builder $query): Builder
    {
        return $query->where('reports.status', 'GENERATED')
            ->whereNotExists(function (QueryBuilder $reviews): void {
                $reviews->selectRaw('1')->from('report_reviews')
                    ->whereColumn('report_reviews.report_id', 'reports.id')
                    ->where(function (QueryBuilder $decisions): void {
                        $decisions->whereNull('status')->orWhereNull('reviewed_at')->orWhereRaw('UPPER(TRIM(status)) <> ?', ['APPROVED']);
                    });
            });
    }

    /**
     * @param  Builder<Report>  $query
     * @return Builder<Report>
     */
    public function accessible(Builder $query, string $userId): Builder
    {
        $user = User::query()->where('uuid', $userId)->first();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->role->value === 'SUPERUSER') {
            return $query;
        }

        return $query->where(function (Builder $visibility) use ($user): void {
            $visibility->where(fn (Builder $public): Builder => $this->consultable($public));

            if ($user->role->value === 'OPERATOR') {
                $visibility->orWhere('reports.created_by', $user->id);
            }

            if ($user->role->value === 'REVIEWER' && $user->coordination_id !== null) {
                $visibility->orWhere(fn (Builder $coordination): Builder => $this->forCoordination($coordination, $user->coordination_id));
            }
        });
    }

    /**
     * @param  Builder<Report>  $query
     * @return Builder<Report>
     */
    public function forCoordination(Builder $query, int $coordinationId): Builder
    {
        return $query->where(function (Builder $coordination) use ($coordinationId): void {
            $coordination->where('reports.coordination_id', $coordinationId)
                ->orWhere(function (Builder $legacy) use ($coordinationId): void {
                    $legacy->whereNull('reports.coordination_id')->whereIn('report_type_id',
                        DB::table('report_types')->select('id')->where('coordination_id', $coordinationId));
                });
        });
    }
}
