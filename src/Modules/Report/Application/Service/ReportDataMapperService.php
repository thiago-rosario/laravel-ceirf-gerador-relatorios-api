<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Service;

use src\Modules\Report\Application\DTO\GeneratedReportDataDTO;
use src\Modules\Report\Application\DTO\MunicipalityDataDTO;
use src\Modules\Report\Application\DTO\ReportAttachmentDataDTO;
use src\Modules\Report\Application\DTO\ReportAttachmentsDataDTO;
use src\Modules\Report\Application\DTO\ReportChecklistDataDTO;
use src\Modules\Report\Application\DTO\ReportConclusionDataDTO;
use src\Modules\Report\Application\DTO\ReportCoverDataDTO;
use src\Modules\Report\Application\DTO\ReportDataDTO;
use src\Modules\Report\Application\DTO\ReportFileReferenceDataDTO;
use src\Modules\Report\Application\DTO\ReportGeneralInformationDataDTO;
use src\Modules\Report\Application\DTO\ReportImageDataDTO;
use src\Modules\Report\Application\DTO\ReportInfrastructureDataDTO;
use src\Modules\Report\Application\DTO\ReportInstitutionalRepresentativeDataDTO;
use src\Modules\Report\Application\DTO\ReportLocationDataDTO;
use src\Modules\Report\Application\DTO\ReportPhotographicDocumentationDataDTO;
use src\Modules\Report\Application\DTO\ReportPreImplementationDataDTO;
use src\Modules\Report\Application\Interfaces\Service\ReportDataMapperServiceInterface;
use src\Modules\Report\Domain\Entity\ReportAttachmentEntity;
use src\Modules\Report\Domain\Entity\ReportEntity;
use src\Modules\Report\Domain\Entity\ReportImageEntity;
use src\Modules\Report\Domain\ValueObject\GeneratedReportValueObject;
use src\Modules\Report\Domain\ValueObject\MunicipalityValueObject;
use src\Modules\Report\Domain\ValueObject\ReportAttachmentsValueObject;
use src\Modules\Report\Domain\ValueObject\ReportChecklistValueObject;
use src\Modules\Report\Domain\ValueObject\ReportConclusionValueObject;
use src\Modules\Report\Domain\ValueObject\ReportCoverValueObject;
use src\Modules\Report\Domain\ValueObject\ReportFileReferenceValueObject;
use src\Modules\Report\Domain\ValueObject\ReportGeneralInformationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportInfrastructureValueObject;
use src\Modules\Report\Domain\ValueObject\ReportInstitutionalRepresentativeValueObject;
use src\Modules\Report\Domain\ValueObject\ReportLocationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPhotographicDocumentationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPreImplementationValueObject;

class ReportDataMapperService implements ReportDataMapperServiceInterface
{
    public function map(ReportEntity $report): ReportDataDTO
    {
        return new ReportDataDTO(
            id: $report->id()->value(),
            rootReportId: $report->rootReportId()->value(),
            parentReportId: $report->parentReportId()?->value(),
            createdBy: $report->createdBy()->value(),
            revisionNumber: $report->revisionNumber(),
            status: $report->status()->value,
            revisionLabel: $report->revisionLabel(),
            isGenerated: $report->isGenerated(),
            isRevision: $report->isRevision(),
            createdAt: $report->createdAt(),
            updatedAt: $report->updatedAt(),
            cover: $this->mapCover($report->cover()),
            generalInformation: $this->mapGeneralInformation($report->generalInformation()),
            location: $this->mapLocation($report->location()),
            infrastructure: $this->mapInfrastructure($report->infrastructure()),
            preImplementation: $this->mapPreImplementation($report->preImplementation()),
            photographicDocumentation: $this->mapPhotographicDocumentation($report->photographicDocumentation()),
            attachments: $this->mapAttachments($report->attachments()),
            conclusion: $this->mapConclusion($report->conclusion()),
            generatedDocument: $this->mapGeneratedDocument($report->generatedDocument()),
            uploadedImages: array_map($this->mapImage(...), $report->uploadedImages()),
            coordinationId: $report->coordinationId(),
            reportTypeId: $report->reportTypeId(),
        );
    }

    private function mapCover(?ReportCoverValueObject $cover): ?ReportCoverDataDTO
    {
        if ($cover === null) {
            return null;
        }

        return new ReportCoverDataDTO(
            municipality: $this->mapMunicipality($cover->municipality()),
            force: $cover->force()?->value,
            size: $cover->size()?->value,
            typology: $cover->typology(),
            seiNumber: $cover->seiNumber()?->value(),
        );
    }

    private function mapMunicipality(?MunicipalityValueObject $municipality): ?MunicipalityDataDTO
    {
        if ($municipality === null) {
            return null;
        }

        return new MunicipalityDataDTO(
            id: $municipality->id(),
            name: $municipality->name(),
            stateCode: $municipality->stateCode(),
        );
    }

    private function mapGeneralInformation(?ReportGeneralInformationValueObject $generalInformation): ?ReportGeneralInformationDataDTO
    {
        if ($generalInformation === null) {
            return null;
        }

        return new ReportGeneralInformationDataDTO(
            inspectionDate: $generalInformation->inspectionDate(),
            collaborators: $generalInformation->collaborators(),
            institutionalRepresentatives: array_map(
                fn (ReportInstitutionalRepresentativeValueObject $representative): ReportInstitutionalRepresentativeDataDTO => new ReportInstitutionalRepresentativeDataDTO(
                    name: $representative->name(),
                    position: $representative->position(),
                ),
                $generalInformation->institutionalRepresentatives(),
            ),
        );
    }

    private function mapLocation(?ReportLocationValueObject $location): ?ReportLocationDataDTO
    {
        if ($location === null) {
            return null;
        }

        return new ReportLocationDataDTO(
            locationMap: $location->locationMap() === null ? null : $this->mapImage($location->locationMap()),
            municipalityInStateMap: $location->municipalityInStateMap() === null ? null : $this->mapImage($location->municipalityInStateMap()),
        );
    }

    private function mapInfrastructure(?ReportInfrastructureValueObject $infrastructure): ?ReportInfrastructureDataDTO
    {
        if ($infrastructure === null) {
            return null;
        }

        return new ReportInfrastructureDataDTO(
            waterNetwork: $infrastructure->waterNetwork()?->value,
            highVoltageNetwork: $infrastructure->highVoltageNetwork()?->value,
            lowVoltageNetwork: $infrastructure->lowVoltageNetwork()?->value,
            sewageNetwork: $infrastructure->sewageNetwork()?->value,
            telephony: $infrastructure->telephony()?->value,
            publicLighting: $infrastructure->publicLighting()?->value,
            internet: $infrastructure->internet()?->value,
            wasteCollection: $infrastructure->wasteCollection()?->value,
            paving: $infrastructure->paving()?->value,
            existingBuildings: $infrastructure->existingBuildings()?->value,
        );
    }

    private function mapPreImplementation(?ReportPreImplementationValueObject $preImplementation): ?ReportPreImplementationDataDTO
    {
        if ($preImplementation === null) {
            return null;
        }

        return new ReportPreImplementationDataDTO(
            image: $preImplementation->image() === null ? null : $this->mapImage($preImplementation->image()),
        );
    }

    private function mapPhotographicDocumentation(?ReportPhotographicDocumentationValueObject $photographicDocumentation): ?ReportPhotographicDocumentationDataDTO
    {
        if ($photographicDocumentation === null) {
            return null;
        }

        return new ReportPhotographicDocumentationDataDTO(
            images: array_map($this->mapImage(...), $photographicDocumentation->images()),
        );
    }

    private function mapAttachments(?ReportAttachmentsValueObject $attachments): ?ReportAttachmentsDataDTO
    {
        if ($attachments === null) {
            return null;
        }

        return new ReportAttachmentsDataDTO(
            checklist: $this->mapChecklist($attachments->checklist()),
            municipalityLocationMap: $attachments->municipalityLocationMap() === null ? null : $this->mapAttachment($attachments->municipalityLocationMap()),
            topographicPlan: $attachments->topographicPlan() === null ? null : $this->mapAttachment($attachments->topographicPlan()),
            others: array_map($this->mapAttachment(...), $attachments->others()),
        );
    }

    private function mapChecklist(?ReportChecklistValueObject $checklist): ?ReportChecklistDataDTO
    {
        if ($checklist === null) {
            return null;
        }

        return new ReportChecklistDataDTO(
            seiConstructionRequest: $checklist->seiConstructionRequest()?->value,
            seiLandAndTypologyIdentification: $checklist->seiLandAndTypologyIdentification()?->value,
            stateOwnedLand: $checklist->stateOwnedLand()?->value,
            simovLegalized: $checklist->simovLegalized()?->value,
            compatibleDimensions: $checklist->compatibleDimensions()?->value,
            slopeOrLevelRisk: $checklist->slopeOrLevelRisk()?->value,
            stormwaterDrainage: $checklist->stormwaterDrainage()?->value,
            floodHistory: $checklist->floodHistory()?->value,
            electricitySupply: $checklist->electricitySupply()?->value,
            waterSupply: $checklist->waterSupply()?->value,
            sewageSupply: $checklist->sewageSupply()?->value,
            pavingAndSidewalk: $checklist->pavingAndSidewalk()?->value,
            regularWasteCollection: $checklist->regularWasteCollection()?->value,
            demolitionRequired: $checklist->demolitionRequired()?->value,
            easyPublicAccess: $checklist->easyPublicAccess()?->value,
            domainStripOrNonBuildableArea: $checklist->domainStripOrNonBuildableArea()?->value,
            technicalFeasibilityReport: $checklist->technicalFeasibilityReport()?->value,
            reportAttachedToSei: $checklist->reportAttachedToSei()?->value,
            worksDashboardUpdated: $checklist->worksDashboardUpdated()?->value,
            environmentalProtectionArea: $checklist->environmentalProtectionArea()?->value,
        );
    }

    private function mapConclusion(?ReportConclusionValueObject $conclusion): ?ReportConclusionDataDTO
    {
        if ($conclusion === null) {
            return null;
        }

        return new ReportConclusionDataDTO(content: $conclusion->content());
    }

    private function mapGeneratedDocument(?GeneratedReportValueObject $document): ?GeneratedReportDataDTO
    {
        if ($document === null) {
            return null;
        }

        return new GeneratedReportDataDTO(
            storageIdentifier: $document->storageIdentifier(),
            fileName: $document->fileName(),
            generatedAt: $document->generatedAt(),
        );
    }

    private function mapImage(ReportImageEntity $image): ReportImageDataDTO
    {
        return new ReportImageDataDTO(
            id: $image->id()->value(),
            file: $this->mapFile($image->file()),
            order: $image->order(),
            caption: $image->caption(),
        );
    }

    private function mapAttachment(ReportAttachmentEntity $attachment): ReportAttachmentDataDTO
    {
        return new ReportAttachmentDataDTO(
            id: $attachment->id()->value(),
            type: $attachment->type()->value,
            file: $this->mapFile($attachment->file()),
            description: $attachment->description(),
        );
    }

    private function mapFile(ReportFileReferenceValueObject $file): ReportFileReferenceDataDTO
    {
        return new ReportFileReferenceDataDTO(
            storageIdentifier: $file->storageIdentifier(),
            fileName: $file->fileName(),
            mimeType: $file->mimeType(),
            sizeBytes: $file->sizeBytes(),
            checksum: $file->checksum(),
        );
    }
}
