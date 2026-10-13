<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\DTO;

use DateTimeImmutable;

readonly class ReportDataDTO
{
    /** @param list<ReportImageDataDTO> $uploadedImages */
    public function __construct(
        public string $id,
        public string $rootReportId,
        public ?string $parentReportId,
        public string $createdBy,
        public int $revisionNumber,
        public string $status,
        public ?string $revisionLabel,
        public bool $isGenerated,
        public bool $isRevision,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?ReportCoverDataDTO $cover = null,
        public ?ReportGeneralInformationDataDTO $generalInformation = null,
        public ?ReportLocationDataDTO $location = null,
        public ?ReportInfrastructureDataDTO $infrastructure = null,
        public ?ReportPreImplementationDataDTO $preImplementation = null,
        public ?ReportPhotographicDocumentationDataDTO $photographicDocumentation = null,
        public ?ReportAttachmentsDataDTO $attachments = null,
        public ?ReportConclusionDataDTO $conclusion = null,
        public ?GeneratedReportDataDTO $generatedDocument = null,
        public array $uploadedImages = [],
        public ?int $coordinationId = null,
        public ?int $reportTypeId = null,
    ) {}
}
