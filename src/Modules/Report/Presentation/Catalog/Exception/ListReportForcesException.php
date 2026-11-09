<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Catalog\Exception;

use RuntimeException;
use src\Modules\Report\Presentation\Catalog\Enum\CodeExceptionEnum;
use Throwable;

class ListReportForcesException extends RuntimeException
{
    public function __construct(
        string $message = 'Erro ao listar as forças!',
        int $code = CodeExceptionEnum::LIST_REPORT_FORCE_ERROR->value,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
