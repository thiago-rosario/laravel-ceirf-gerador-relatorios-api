<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Catalog\Interfaces\Usecase;

interface ListReportAnswersUsecaseInterface
{
    /** @return list<array{key: string, label: string}> */
    public function __invoke(): array;
}
