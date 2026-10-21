<?php

declare(strict_types=1);

namespace src\Modules\Report\Domain\Exception;

use RuntimeException;
use src\Modules\Report\Domain\Enum\CodeExceptionEnum;
use Throwable;

class ReportImageTooLargeException extends RuntimeException
{
    public function __construct(
        string $message = 'Cada imagem do relatório deve possuir no máximo 25 MB.',
        int $code = CodeExceptionEnum::REPORT_IMAGE_TOO_LARGE->value,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
