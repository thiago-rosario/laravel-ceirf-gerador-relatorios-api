<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Catalog\Exception;

use src\Modules\Report\Presentation\Catalog\Enum\CodeExceptionEnum;
use Throwable;

class ForceNotFoundException extends ListReportForcesException
{
    public function __construct(
        string $message = 'Força não encontrada !',
        int $code = CodeExceptionEnum::FORCE_NOT_FOUND->value,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
