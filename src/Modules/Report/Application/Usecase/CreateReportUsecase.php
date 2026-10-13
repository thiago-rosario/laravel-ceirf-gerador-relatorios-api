<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Usecase;

use src\Modules\Report\Application\DTO\CreateReportInputDTO;
use src\Modules\Report\Application\DTO\CreateReportOutputDTO;
use src\Modules\Report\Application\Interfaces\Mapper\ReportChecklistMapperInterface;
use src\Modules\Report\Application\Interfaces\Service\ReportAccessServiceInterface;
use src\Modules\Report\Application\Interfaces\Usecase\CreateReportUsecaseInterface;
use src\Modules\Report\Domain\Entity\ReportEntity;
use src\Modules\Report\Domain\Repository\ReportRepositoryInterface;
use src\Modules\Report\Domain\ValueObject\ReportAttachmentsValueObject;

class CreateReportUsecase implements CreateReportUsecaseInterface
{
    public function __construct(
        private readonly ReportRepositoryInterface $repository,
        private readonly ReportChecklistMapperInterface $mapper,
        private readonly ReportAccessServiceInterface $access,
    ) {}

    public function __invoke(CreateReportInputDTO $input): CreateReportOutputDTO
    {
        $checklist = $this->mapper->map($input);
        $attachments = new ReportAttachmentsValueObject(checklist: $checklist);
        $scope = $this->access->creationScope($input->createdBy, $input->reportTypeId, $input->coordinationId);

        $report = new ReportEntity(
            createdBy: $input->createdBy,
            cover: $input->cover,
            generalInformation: $input->generalInformation,
            location: $input->location,
            infrastructure: $input->infrastructure,
            preImplementation: $input->preImplementation,
            photographicDocumentation: $input->photographicDocumentation,
            attachments: $attachments,
            conclusion: $input->conclusion,
            coordinationId: $scope->coordinationId,
            reportTypeId: $scope->reportTypeId,
        );

        $reportCreated = $this->repository->insert($report);

        return new CreateReportOutputDTO(
            id: $reportCreated->id()->value(),
            status: $reportCreated->status()->value,
            revisionNumber: $reportCreated->revisionNumber(),
            createdAt: $reportCreated->createdAt(),
        );
    }
}
