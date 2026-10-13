<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\DTO;

use src\Modules\Report\Domain\ValueObject\ReportConclusionValueObject;
use src\Modules\Report\Domain\ValueObject\ReportCoverValueObject;
use src\Modules\Report\Domain\ValueObject\ReportGeneralInformationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportInfrastructureValueObject;
use src\Modules\Report\Domain\ValueObject\ReportLocationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPhotographicDocumentationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPreImplementationValueObject;

readonly class CreateReportInputDTO
{
    public function __construct(
        public string $createdBy,
        public ?ReportCoverValueObject $cover = null,
        public ?ReportGeneralInformationValueObject $generalInformation = null,
        public ?ReportLocationValueObject $location = null,
        public ?ReportInfrastructureValueObject $infrastructure = null,
        public ?ReportPreImplementationValueObject $preImplementation = null,
        public ?ReportPhotographicDocumentationValueObject $photographicDocumentation = null,
        public ?ReportConclusionValueObject $conclusion = null,
        public ?string $seiConstructionRequest = null,
        public ?string $seiLandAndTypologyIdentification = null,
        public ?string $stateOwnedLand = null,
        public ?string $simovLegalized = null,
        public ?string $compatibleDimensions = null,
        public ?string $slopeOrLevelRisk = null,
        public ?string $stormwaterDrainage = null,
        public ?string $floodHistory = null,
        public ?string $electricitySupply = null,
        public ?string $waterSupply = null,
        public ?string $sewageSupply = null,
        public ?string $pavingAndSidewalk = null,
        public ?string $regularWasteCollection = null,
        public ?string $demolitionRequired = null,
        public ?string $easyPublicAccess = null,
        public ?string $domainStripOrNonBuildableArea = null,
        public ?string $technicalFeasibilityReport = null,
        public ?string $reportAttachedToSei = null,
        public ?string $worksDashboardUpdated = null,
        public ?string $environmentalProtectionArea = null,
        public ?int $coordinationId = null,
        public ?int $reportTypeId = null,
    ) {}
}
