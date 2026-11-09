<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Catalog\Exception;

use RuntimeException;
use src\Modules\Report\Presentation\Catalog\Enum\CodeExceptionEnum;
use Throwable;

class ListReportChecklistException extends RuntimeException
{
    public function __construct(
        string $message = 'Erro ao listar o checklist!',
        int $code = CodeExceptionEnum::ListReportChecklistError->value,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
