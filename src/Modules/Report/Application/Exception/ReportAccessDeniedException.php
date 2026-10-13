<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Exception;

use RuntimeException;

class ReportAccessDeniedException extends RuntimeException
{
    public function __construct(string $message = 'Você não tem permissão para acessar este relatório.')
    {
        parent::__construct($message, 403);
    }
}
