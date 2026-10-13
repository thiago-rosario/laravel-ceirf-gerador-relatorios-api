<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Usecase;

use src\Modules\Report\Application\DTO\SearchReportsInputDTO;
use src\Modules\Report\Application\DTO\SearchReportsOutputDTO;
use src\Modules\Report\Application\Interfaces\Repository\ReportConsultationRepositoryInterface;
use src\Modules\Report\Application\Interfaces\Usecase\SearchReportsUsecaseInterface;

class SearchReportsUsecase implements SearchReportsUsecaseInterface
{
    public function __construct(private readonly ReportConsultationRepositoryInterface $repository) {}

    public function __invoke(SearchReportsInputDTO $input): SearchReportsOutputDTO
    {
        return $this->repository->search($input);
    }
}
