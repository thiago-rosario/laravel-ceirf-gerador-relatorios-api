<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Catalog\Usecase;

use src\Modules\Report\Application\Catalog\DTO\ListReportTypologiesInputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportTypologiesOutputDTO;
use src\Modules\Report\Application\Catalog\Interfaces\Repository\ReportCatalogRepositoryInterface;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportTypologiesUsecaseInterface;
use src\Modules\Report\Domain\Enum\ReportSizeEnum;

class ListReportTypologiesUsecase implements ListReportTypologiesUsecaseInterface
{
    public function __construct(
        private readonly ReportCatalogRepositoryInterface $repository
    ) {}

    public function __invoke(ListReportTypologiesInputDTO $input): ListReportTypologiesOutputDTO
    {
        $typologies = $this->repository->typologies();

        if ($typologies === []) {
            return new ListReportTypologiesOutputDTO(items: []);
        }

        $typologiesByName = [];

        foreach ($typologies as $typology) {
            $typologiesByName[mb_strtoupper((string) $typology->name)] ??= $typology;
        }

        $items = [];

        foreach (ReportSizeEnum::cases() as $size) {
            $record = $typologiesByName[$size->value] ?? null;

            if ($record !== null) {
                $items[] = [
                    'id' => (int) $record->id,
                    'code' => $size->value,
                    'name' => $size->value,
                ];
            }
        }

        return new ListReportTypologiesOutputDTO(
            items: $items,
        );
    }
}
