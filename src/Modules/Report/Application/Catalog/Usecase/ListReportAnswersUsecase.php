<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Catalog\Usecase;

use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportAnswersUsecaseInterface;
use src\Modules\Report\Domain\Enum\ChecklistAnswerEnum;

class ListReportAnswersUsecase implements ListReportAnswersUsecaseInterface
{
    /** @return list<array{key: string, label: string}> */
    public function __invoke(): array
    {
        return array_map(fn (ChecklistAnswerEnum $answer): array => ['key' => $answer->value, 'label' => $answer->value], ChecklistAnswerEnum::cases());
    }
}
