<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Catalog\Interfaces\Repository;

use src\Modules\Report\Domain\Enum\ForceEnum;
use src\Modules\Report\Domain\Enum\ReportSizeEnum;

interface ReportCatalogRepositoryInterface
{
    /** @return list<array{key: string, label: string}> */
    public function forces(): array;

    /** @return list<array{key: string, label: string}> */
    public function typologies(): array;

    public function forceId(ForceEnum $force): ?int;

    public function typologyId(ReportSizeEnum $size): ?int;
}
