<?php

declare(strict_types=1);

namespace src\Modules\Report\Infra\Catalog\Repository;

use Illuminate\Support\Facades\DB;
use src\Modules\Report\Application\Catalog\Interfaces\Repository\ReportCatalogRepositoryInterface;
use src\Modules\Report\Domain\Enum\ForceEnum;
use src\Modules\Report\Domain\Enum\ReportSizeEnum;
use stdClass;

class ReportCatalogRepository implements ReportCatalogRepositoryInterface
{
    /** @return list<stdClass> */
    public function forces(): array
    {
        return array_values(DB::table('forces')->orderBy('id')->get(['id', 'code', 'name'])->all());
    }

    /** @return list<stdClass> */
    public function typologies(): array
    {
        return array_values(DB::table('sizes')->orderBy('id')->get(['id', 'name'])->all());
    }

    public function forceId(ForceEnum $force): ?int
    {
        $id = DB::table('forces')->whereIn('code', [$force->databaseCode(), $force->value])
            ->orderByRaw('CASE WHEN code = ? THEN 0 ELSE 1 END', [$force->databaseCode()])
            ->orderBy('id')->value('id');

        return $id === null ? null : (int) $id;
    }

    public function typologyId(ReportSizeEnum $size): ?int
    {
        foreach (DB::table('sizes')->orderBy('id')->get(['id', 'name']) as $record) {
            if (mb_strtoupper((string) $record->name) === $size->value) {
                return (int) $record->id;
            }
        }

        return null;
    }
}
