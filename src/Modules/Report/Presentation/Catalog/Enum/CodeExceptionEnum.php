<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Catalog\Enum;

enum CodeExceptionEnum: int
{
    case LIST_REPORT_FORCE_ERROR = 2301;

    case FORCE_NOT_FOUND = 2302;

    case ListReportAnswersError = 2303;

    case ListReportChecklistError = 2304;

    case ListReportInfrastructureError = 2305;

    case ListReportTypologiesError = 2306;
}
