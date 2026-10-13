<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Interfaces\Repository;

use src\Modules\Report\Application\DTO\SearchReportsInputDTO;
use src\Modules\Report\Application\DTO\SearchReportsOutputDTO;

interface ReportConsultationRepositoryInterface
{
    public function search(SearchReportsInputDTO $input): SearchReportsOutputDTO;

}
