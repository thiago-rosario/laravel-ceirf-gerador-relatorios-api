<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Application\Interfaces\Usecase;

use src\Modules\Attachment\Application\DTO\GetMediaReportInputDTO;
use src\Modules\Attachment\Application\DTO\GetMediaReportOutputDTO;

interface GetMediaReportUsecaseInterface
{
    public function __invoke(GetMediaReportInputDTO $input): GetMediaReportOutputDTO;
}
