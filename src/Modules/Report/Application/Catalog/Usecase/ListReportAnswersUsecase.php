<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Catalog\Usecase;

use src\Modules\Report\Application\Catalog\DTO\ListReportAnswersInputDTO;
use src\Modules\Report\Application\Catalog\DTO\ListReportAnswersOutputDTO;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportAnswersUsecaseInterface;
use src\Modules\Report\Domain\Enum\ChecklistAnswerEnum;

class ListReportAnswersUsecase implements ListReportAnswersUsecaseInterface
{
    public function __invoke(ListReportAnswersInputDTO $input): ListReportAnswersOutputDTO
    {
        $items = [];

        foreach (ChecklistAnswerEnum::cases() as $answer) {
            $items[] = [
                'code' => $answer->value,
                'name' => $answer->value,
            ];
        }

        return new ListReportAnswersOutputDTO(
            items: $items,
        );
    }
}
