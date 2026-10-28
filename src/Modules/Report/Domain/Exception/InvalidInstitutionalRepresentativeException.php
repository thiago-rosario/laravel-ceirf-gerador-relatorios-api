<?php

declare(strict_types=1);

namespace src\Modules\Report\Domain\Exception;

use RuntimeException;
use src\Modules\Report\Domain\Enum\CodeExceptionEnum;
use Throwable;

class InvalidInstitutionalRepresentativeException extends RuntimeException
{
    public function __construct(
        string $message = 'O nome e o cargo do representante institucional devem ser informados e possuir no máximo 255 caracteres cada.',
        int $code = CodeExceptionEnum::INVALID_INSTITUCIONAL_REPRESENTATIVE->value,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
