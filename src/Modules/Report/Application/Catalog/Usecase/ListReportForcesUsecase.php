<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Catalog\Usecase;

use src\Modules\Report\Application\Catalog\DTO\ListReportForcesInputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportForcesOutputDTO;
use src\Modules\Report\Application\Catalog\Interfaces\Repository\ReportCatalogRepositoryInterface;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportForcesUsecaseInterface;
use src\Modules\Report\Domain\Enum\ForceEnum;
use src\Modules\Report\Presentation\Catalog\Exception\ForceNotFoundException;

class ListReportForcesUsecase implements ListReportForcesUsecaseInterface
{
    public function __construct(
        private readonly ReportCatalogRepositoryInterface $repository
    ) {}

    public function __invoke(ListReportForcesInputDTO $input): ListReportForcesOutputDTO
    {
        $forces = $this->repository->forces();

        if ($forces === []) {
            throw new ForceNotFoundException;
        }

        $forcesByCode = [];

        foreach ($forces as $force) {
            $forcesByCode[(string) $force->code] = $force;
        }

        $items = [];

        foreach (ForceEnum::cases() as $force) {
            $record = $forcesByCode[$force->databaseCode()] ?? $forcesByCode[$force->value] ?? null;

            if ($record !== null) {
                $items[] = [
                    'id' => (int) $record->id,
                    'code' => $force->value,
                    'name' => (string) $record->name,
                ];
            }
        }

        return new ListReportForcesOutputDTO(
            items: $items,
        );
    }
}
