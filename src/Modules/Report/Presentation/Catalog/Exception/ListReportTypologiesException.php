<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Catalog\Exception;

use RuntimeException;
use src\Modules\Report\Presentation\Catalog\Enum\CodeExceptionEnum;
use Throwable;

class ListReportTypologiesException extends RuntimeException
{
    public function __construct(
        string $message = 'Erro ao listar as tipologias!',
        int $code = CodeExceptionEnum::ListReportTypologiesError->value,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
