<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Usecase;

use src\Modules\Report\Application\DTO\FindByIdReportInputDTO;
use src\Modules\Report\Application\DTO\ReportPdfFileDTO;
use src\Modules\Report\Application\Exception\ReportPdfUnavailableException;
use src\Modules\Report\Application\Interfaces\Service\ReportPdfStorageInterface;
use src\Modules\Report\Application\Interfaces\Usecase\FindByIdReportUsecaseInterface;
use src\Modules\Report\Application\Interfaces\Usecase\GetReportPdfUsecaseInterface;

class GetReportPdfUsecase implements GetReportPdfUsecaseInterface
{
    public function __construct(
        private readonly FindByIdReportUsecaseInterface $finder,
        private readonly ReportPdfStorageInterface $storage,
    ) {}

    public function __invoke(FindByIdReportInputDTO $input): ReportPdfFileDTO
    {
        $report = $this->finder->__invoke($input)->report;

        if ($report->generatedDocument === null) {
            throw new ReportPdfUnavailableException('O PDF deste relatório ainda não foi gerado.', 409);
        }

        return $this->storage->open($report->id, $report->generatedDocument);
    }
}
