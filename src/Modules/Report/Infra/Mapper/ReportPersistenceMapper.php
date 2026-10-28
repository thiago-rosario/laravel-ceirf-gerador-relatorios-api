<?php

declare(strict_types=1);

namespace src\Modules\Report\Infra\Mapper;

use DateTimeImmutable;
use src\Modules\Report\Application\Interfaces\Mapper\ReportPersistenceMapperInterface;
use src\Modules\Report\Domain\Entity\ReportEntity;
use src\Modules\Report\Domain\Enum\ReportStatusEnum;
use src\Modules\Report\Domain\ValueObject\GeneratedReportValueObject;
use src\Modules\Report\Domain\ValueObject\ReportConclusionValueObject;
use src\Modules\Report\Domain\ValueObject\ReportGeneralInformationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportInfrastructureValueObject;
use src\Modules\Report\Domain\ValueObject\ReportInstitutionalRepresentativeValueObject;
use src\Modules\Report\Domain\ValueObject\ReportLocationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPhotographicDocumentationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPreImplementationValueObject;
use src\Modules\Report\Infra\Trait\MapsReportPersistenceMediaTrait;
use src\Modules\Report\Infra\Trait\MapsReportPersistenceSectionsTrait;

/**
 * @phpstan-type FilePayload array{storageIdentifier: string, fileName: string, mimeType: string, sizeBytes: int, checksum: string}
 * @phpstan-type ImagePayload array{id: string, file: FilePayload, order: int, caption: string}
 * @phpstan-type AttachmentPayload array{id: string, type: string, file: FilePayload, description: string}
 * @phpstan-type CoverPayload array{municipality: array{id: int, name: string, stateCode: string}|null, force: string|null, size: string|null, typology: string, seiNumber: string|null}
 * @phpstan-type AttachmentsPayload array{checklist: array<string, string|null>|null, municipalityLocationMap: AttachmentPayload|null, topographicPlan: AttachmentPayload|null, others: list<AttachmentPayload>}
 * @phpstan-type ReportPayload array{
 *     id: string,
 *     rootReportId: string,
 *     parentReportId: string|null,
 *     createdBy: string,
 *     revisionNumber: int,
 *     status: string,
 *     createdAt: string,
 *     updatedAt: string,
 *     cover: CoverPayload|null,
 *     generalInformation: array{inspectionDate: string|null, collaborators: string, institutionalRepresentatives?: list<array{name: string, position: string}>}|null,
 *     location: array{locationMap: ImagePayload|null, municipalityInStateMap: ImagePayload|null}|null,
 *     infrastructure: array<string, string|null>|null,
 *     preImplementation: array{image: ImagePayload|null}|null,
 *     photographicDocumentation: array{images: list<ImagePayload>}|null,
 *     attachments: AttachmentsPayload|null,
 *     conclusion: array{content: string}|null,
 *     generatedDocument: array{storageIdentifier: string, fileName: string, generatedAt: string}|null,
 *     uploadedImages: list<ImagePayload>,
 *     coordinationId?: int|null,
 *     reportTypeId?: int|null
 * }
 */
class ReportPersistenceMapper implements ReportPersistenceMapperInterface
{
    use MapsReportPersistenceMediaTrait;
    use MapsReportPersistenceSectionsTrait;

    /** @var array<string, string> */
    private const INFRASTRUCTURE_COLUMNS = [
        'waterNetwork' => 'infrastructure_water_network',
        'highVoltageNetwork' => 'infrastructure_high_voltage_network',
        'lowVoltageNetwork' => 'infrastructure_low_voltage_network',
        'sewageNetwork' => 'infrastructure_sewage_network',
        'telephony' => 'infrastructure_telephony',
        'publicLighting' => 'infrastructure_public_lighting',
        'internet' => 'infrastructure_internet',
        'wasteCollection' => 'infrastructure_waste_collection',
        'paving' => 'infrastructure_paving',
        'existingBuildings' => 'infrastructure_existing_buildings',
    ];

    /** @var array<string, string> */
    private const CHECKLIST_COLUMNS = [
        'seiConstructionRequest' => 'checklist_sei_construction_request',
        'seiLandAndTypologyIdentification' => 'checklist_sei_land_and_typology_identification',
        'stateOwnedLand' => 'checklist_state_owned_land',
        'simovLegalized' => 'checklist_simov_legalized',
        'compatibleDimensions' => 'checklist_compatible_dimensions',
        'slopeOrLevelRisk' => 'checklist_slope_or_level_risk',
        'stormwaterDrainage' => 'checklist_stormwater_drainage',
        'floodHistory' => 'checklist_flood_history',
        'electricitySupply' => 'checklist_electricity_supply',
        'waterSupply' => 'checklist_water_supply',
        'sewageSupply' => 'checklist_sewage_supply',
        'pavingAndSidewalk' => 'checklist_paving_and_sidewalk',
        'regularWasteCollection' => 'checklist_regular_waste_collection',
        'demolitionRequired' => 'checklist_demolition_required',
        'easyPublicAccess' => 'checklist_easy_public_access',
        'domainStripOrNonBuildableArea' => 'checklist_domain_strip_or_non_buildable_area',
        'technicalFeasibilityReport' => 'checklist_technical_feasibility_report',
        'reportAttachedToSei' => 'checklist_report_attached_to_sei',
        'worksDashboardUpdated' => 'checklist_works_dashboard_updated',
        'environmentalProtectionArea' => 'checklist_environmental_protection_area',
    ];

    /** @return array<string, mixed> */
    public function toPayload(ReportEntity $report): array
    {
        $generalInformation = $report->generalInformation();
        $location = $report->location();
        $infrastructure = $report->infrastructure();
        $preImplementation = $report->preImplementation();
        $photographicDocumentation = $report->photographicDocumentation();
        $conclusion = $report->conclusion();
        $document = $report->generatedDocument();

        return [
            'id' => $report->id()->value(),
            'rootReportId' => $report->rootReportId()->value(),
            'parentReportId' => $report->parentReportId()?->value(),
            'createdBy' => $report->createdBy()->value(),
            'revisionNumber' => $report->revisionNumber(),
            'status' => $report->status()->value,
            'coordinationId' => $report->coordinationId(),
            'reportTypeId' => $report->reportTypeId(),
            'createdAt' => $this->date($report->createdAt()),
            'updatedAt' => $this->date($report->updatedAt()),
            'cover' => $this->toCover($report->cover()),
            'generalInformation' => $generalInformation === null ? null : [
                'inspectionDate' => $generalInformation->inspectionDate() === null ? null : $this->date($generalInformation->inspectionDate()),
                'collaborators' => $generalInformation->collaborators(),
                'institutionalRepresentatives' => array_map(
                    fn (ReportInstitutionalRepresentativeValueObject $representative): array => [
                        'name' => $representative->name(),
                        'position' => $representative->position(),
                    ],
                    $generalInformation->institutionalRepresentatives(),
                ),
            ],
            'location' => $location === null ? null : [
                'locationMap' => $location->locationMap() === null ? null : $this->toImage($location->locationMap()),
                'municipalityInStateMap' => $location->municipalityInStateMap() === null ? null : $this->toImage($location->municipalityInStateMap()),
            ],
            'infrastructure' => $infrastructure === null ? null : $this->toAnswers($infrastructure->answers()),
            'preImplementation' => $preImplementation === null ? null : [
                'image' => $preImplementation->image() === null ? null : $this->toImage($preImplementation->image()),
            ],
            'photographicDocumentation' => $photographicDocumentation === null ? null : [
                'images' => array_map($this->toImage(...), $photographicDocumentation->images()),
            ],
            'attachments' => $this->toAttachments($report->attachments()),
            'conclusion' => $conclusion === null ? null : ['content' => $conclusion->content()],
            'generatedDocument' => $document === null ? null : [
                'storageIdentifier' => $document->storageIdentifier(),
                'fileName' => $document->fileName(),
                'generatedAt' => $this->date($document->generatedAt()),
            ],
            'uploadedImages' => array_map($this->toImage(...), $report->uploadedImages()),
        ];
    }

    /** @param array<string, mixed> $payload */
    public function fromPayload(array $payload): ReportEntity
    {
        /** @var ReportPayload $payload */
        $generalInformation = $payload['generalInformation'];
        $location = $payload['location'];
        $infrastructure = $payload['infrastructure'];
        $preImplementation = $payload['preImplementation'];
        $photographicDocumentation = $payload['photographicDocumentation'];
        $conclusion = $payload['conclusion'];
        $document = $payload['generatedDocument'];

        return new ReportEntity(
            createdBy: $payload['createdBy'],
            id: $payload['id'],
            rootReportId: $payload['rootReportId'],
            parentReportId: $payload['parentReportId'],
            revisionNumber: $payload['revisionNumber'],
            status: ReportStatusEnum::from($payload['status']),
            cover: $this->fromCover($payload['cover']),
            generalInformation: $generalInformation === null ? null : new ReportGeneralInformationValueObject(
                inspectionDate: $generalInformation['inspectionDate'] === null ? null : new DateTimeImmutable($generalInformation['inspectionDate']),
                collaborators: $generalInformation['collaborators'],
                institutionalRepresentatives: array_map(
                    fn (array $representative): ReportInstitutionalRepresentativeValueObject => new ReportInstitutionalRepresentativeValueObject(
                        name: $representative['name'],
                        position: $representative['position'],
                    ),
                    $generalInformation['institutionalRepresentatives'] ?? [],
                ),
            ),
            location: $location === null ? null : new ReportLocationValueObject(
                locationMap: $location['locationMap'] === null ? null : $this->fromImage($location['locationMap']),
                municipalityInStateMap: $location['municipalityInStateMap'] === null ? null : $this->fromImage($location['municipalityInStateMap']),
            ),
            infrastructure: $infrastructure === null ? null : new ReportInfrastructureValueObject(...$this->fromAnswers($infrastructure)),
            preImplementation: $preImplementation === null ? null : new ReportPreImplementationValueObject(
                image: $preImplementation['image'] === null ? null : $this->fromImage($preImplementation['image']),
            ),
            photographicDocumentation: $photographicDocumentation === null ? null : new ReportPhotographicDocumentationValueObject(
                images: array_map($this->fromImage(...), $photographicDocumentation['images']),
            ),
            attachments: $this->fromAttachments($payload['attachments']),
            conclusion: $conclusion === null ? null : new ReportConclusionValueObject($conclusion['content']),
            generatedDocument: $document === null ? null : new GeneratedReportValueObject(
                storageIdentifier: $document['storageIdentifier'],
                fileName: $document['fileName'],
                generatedAt: new DateTimeImmutable($document['generatedAt']),
            ),
            createdAt: $payload['createdAt'],
            updatedAt: $payload['updatedAt'],
            uploadedImages: array_map($this->fromImage(...), $payload['uploadedImages']),
            coordinationId: $payload['coordinationId'] ?? null,
            reportTypeId: $payload['reportTypeId'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(ReportEntity $report): array
    {
        $attributes = [
            'typology' => $report->cover()?->typology(),
            'sei_number' => $report->cover()?->seiNumber()?->value(),
            'inspection_date' => $report->generalInformation()?->inspectionDate()?->format('Y-m-d'),
            'present_collaborators' => $report->generalInformation()?->collaborators(),
            'conclusion' => $report->conclusion()?->content(),
        ];

        $infrastructureAnswers = $report->infrastructure()?->answers() ?? [];
        $checklistAnswers = $report->attachments()?->checklist()?->answers() ?? [];

        foreach (self::INFRASTRUCTURE_COLUMNS as $answer => $column) {
            $attributes[$column] = $this->answerCode($infrastructureAnswers[$answer] ?? null);
        }

        foreach (self::CHECKLIST_COLUMNS as $answer => $column) {
            $attributes[$column] = $this->answerCode($checklistAnswers[$answer] ?? null);
        }

        return $attributes;
    }
}
