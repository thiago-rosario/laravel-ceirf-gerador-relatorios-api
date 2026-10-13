<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Interfaces\Usecase;

use src\Modules\Report\Application\DTO\SearchReportsInputDTO;
use src\Modules\Report\Application\DTO\SearchReportsOutputDTO;

interface SearchReportsUsecaseInterface
{
    public function __invoke(SearchReportsInputDTO $input): SearchReportsOutputDTO;
}
