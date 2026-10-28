<?php

declare(strict_types=1);

namespace src\Modules\Report\Infra\Catalog\Repository;

use Illuminate\Support\Facades\DB;
use src\Modules\Report\Application\Catalog\Interfaces\Repository\ReportCatalogRepositoryInterface;
use src\Modules\Report\Domain\Enum\ForceEnum;
use src\Modules\Report\Domain\Enum\ReportSizeEnum;

class ReportCatalogRepository implements ReportCatalogRepositoryInterface
{
    /** @return list<array{key: string, label: string}> */
    public function forces(): array
    {
        $codes = DB::table('forces')->pluck('code')->all();
        $options = [];

        foreach (ForceEnum::cases() as $force) {
            if (in_array($force->databaseCode(), $codes, true) || in_array($force->value, $codes, true)) {
                $options[] = ['key' => $force->value, 'label' => $force->value];
            }
        }

        return $options;
    }

    /** @return list<array{key: string, label: string}> */
    public function typologies(): array
    {
        $names = DB::table('sizes')->pluck('name')->map(fn (string $name): string => mb_strtoupper($name))->all();
        $options = [];

        foreach (ReportSizeEnum::cases() as $size) {
            if (in_array($size->value, $names, true)) {
                $options[] = ['key' => $size->value, 'label' => $size->value];
            }
        }

        return $options;
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
