<?php

declare(strict_types=1);

namespace src\Modules\Report\Infra\Adapter;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use src\Modules\Report\Application\DTO\CreateReportInputDTO;
use src\Modules\Report\Application\DTO\UpdateReportInputDTO;
use src\Modules\Report\Application\Interfaces\Adapter\ReportInputAdapterInterface;
use src\Modules\Report\Application\Interfaces\Service\ReportFinderServiceInterface;
use src\Modules\Report\Domain\Entity\ReportAttachmentEntity;
use src\Modules\Report\Domain\Entity\ReportImageEntity;
use src\Modules\Report\Domain\Enum\ChecklistAnswerEnum;
use src\Modules\Report\Domain\Enum\ForceEnum;
use src\Modules\Report\Domain\Enum\ReportAttachmentTypeEnum;
use src\Modules\Report\Domain\Enum\ReportSizeEnum;
use src\Modules\Report\Domain\Exception\InvalidMunicipalityException;
use src\Modules\Report\Domain\Exception\InvalidReportFileReferenceException;
use src\Modules\Report\Domain\ValueObject\MunicipalityValueObject;
use src\Modules\Report\Domain\ValueObject\ReportAttachmentsValueObject;
use src\Modules\Report\Domain\ValueObject\ReportChecklistValueObject;
use src\Modules\Report\Domain\ValueObject\ReportConclusionValueObject;
use src\Modules\Report\Domain\ValueObject\ReportCoverValueObject;
use src\Modules\Report\Domain\ValueObject\ReportFileReferenceValueObject;
use src\Modules\Report\Domain\ValueObject\ReportGeneralInformationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportInfrastructureValueObject;
use src\Modules\Report\Domain\ValueObject\ReportLocationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPhotographicDocumentationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPreImplementationValueObject;
use src\Modules\Report\Domain\ValueObject\SeiNumberValueObject;

/**
 * @phpstan-import-type ReportPayload from ReportInputAdapterInterface
 * @phpstan-import-type ReportUpdatePayload from ReportInputAdapterInterface
 * @phpstan-import-type CoverInput from ReportInputAdapterInterface
 * @phpstan-import-type FileInput from ReportInputAdapterInterface
 * @phpstan-import-type ImageInput from ReportInputAdapterInterface
 * @phpstan-import-type AttachmentInput from ReportInputAdapterInterface
 * @phpstan-import-type AttachmentsInput from ReportInputAdapterInterface
 */
class ReportInputAdapter implements ReportInputAdapterInterface
{
    public function __construct(private readonly ReportFinderServiceInterface $finder) {}

    /** @param ReportPayload $data */
    public function create(array $data, string $createdBy): CreateReportInputDTO
    {
        $sections = $this->sections($data);

        return new CreateReportInputDTO(
            createdBy: $createdBy,
            coordinationId: isset($data['coordination_id']) ? (int) $data['coordination_id'] : null,
            reportTypeId: isset($data['report_type_id']) ? (int) $data['report_type_id'] : null,
            cover: $sections['cover'],
            generalInformation: $sections['generalInformation'],
            location: $sections['location'],
            infrastructure: $sections['infrastructure'],
            preImplementation: $sections['preImplementation'],
            photographicDocumentation: $sections['photographicDocumentation'],
            conclusion: $sections['conclusion'],
            seiConstructionRequest: $data['attachments']['checklist']['sei_construction_request'] ?? null,
            seiLandAndTypologyIdentification: $data['attachments']['checklist']['sei_land_and_typology_identification'] ?? null,
            stateOwnedLand: $data['attachments']['checklist']['state_owned_land'] ?? null,
            simovLegalized: $data['attachments']['checklist']['simov_legalized'] ?? null,
            compatibleDimensions: $data['attachments']['checklist']['compatible_dimensions'] ?? null,
            slopeOrLevelRisk: $data['attachments']['checklist']['slope_or_level_risk'] ?? null,
            stormwaterDrainage: $data['attachments']['checklist']['stormwater_drainage'] ?? null,
            floodHistory: $data['attachments']['checklist']['flood_history'] ?? null,
            electricitySupply: $data['attachments']['checklist']['electricity_supply'] ?? null,
            waterSupply: $data['attachments']['checklist']['water_supply'] ?? null,
            sewageSupply: $data['attachments']['checklist']['sewage_supply'] ?? null,
            pavingAndSidewalk: $data['attachments']['checklist']['paving_and_sidewalk'] ?? null,
            regularWasteCollection: $data['attachments']['checklist']['regular_waste_collection'] ?? null,
            demolitionRequired: $data['attachments']['checklist']['demolition_required'] ?? null,
            easyPublicAccess: $data['attachments']['checklist']['easy_public_access'] ?? null,
            domainStripOrNonBuildableArea: $data['attachments']['checklist']['domain_strip_or_non_buildable_area'] ?? null,
            technicalFeasibilityReport: $data['attachments']['checklist']['technical_feasibility_report'] ?? null,
            reportAttachedToSei: $data['attachments']['checklist']['report_attached_to_sei'] ?? null,
            worksDashboardUpdated: $data['attachments']['checklist']['works_dashboard_updated'] ?? null,
            environmentalProtectionArea: $data['attachments']['checklist']['environmental_protection_area'] ?? null,
        );
    }

    /** @param ReportUpdatePayload $data */
    public function update(array $data): UpdateReportInputDTO
    {
        $report = $this->finder->findById($data['id']);
        $rootReportId = $report->rootReportId()->value();
        $sections = $this->sections($data, $rootReportId);

        return new UpdateReportInputDTO(
            id: $data['id'],
            cover: $sections['cover'],
            generalInformation: $sections['generalInformation'],
            location: $sections['location'],
            infrastructure: $sections['infrastructure'],
            preImplementation: $sections['preImplementation'],
            photographicDocumentation: $sections['photographicDocumentation'],
            conclusion: $sections['conclusion'],
            attachments: isset($data['attachments']) ? $this->attachments($data['attachments'], $rootReportId) : null,
        );
    }

    /**
     * @param  ReportPayload  $data
     * @return array{
     *     cover: ReportCoverValueObject|null,
     *     generalInformation: ReportGeneralInformationValueObject|null,
     *     location: ReportLocationValueObject|null,
     *     infrastructure: ReportInfrastructureValueObject|null,
     *     preImplementation: ReportPreImplementationValueObject|null,
     *     photographicDocumentation: ReportPhotographicDocumentationValueObject|null,
     *     conclusion: ReportConclusionValueObject|null
     * }
     */
    private function sections(array $data, ?string $rootReportId = null): array
    {
        return [
            'cover' => isset($data['cover']) ? $this->cover($data['cover']) : null,
            'generalInformation' => isset($data['general_information']) ? new ReportGeneralInformationValueObject(
                inspectionDate: isset($data['general_information']['inspection_date']) ? new DateTimeImmutable($data['general_information']['inspection_date']) : null,
                collaborators: $data['general_information']['collaborators'] ?? '',
            ) : null,
            'location' => isset($data['location']) ? new ReportLocationValueObject(
                locationMap: isset($data['location']['location_map']) ? $this->image($data['location']['location_map'], $rootReportId) : null,
                municipalityInStateMap: isset($data['location']['municipality_in_state_map']) ? $this->image($data['location']['municipality_in_state_map'], $rootReportId) : null,
            ) : null,
            'infrastructure' => isset($data['infrastructure']) ? new ReportInfrastructureValueObject(...$this->answers($data['infrastructure'])) : null,
            'preImplementation' => isset($data['pre_implementation']) ? new ReportPreImplementationValueObject(
                isset($data['pre_implementation']['image']) ? $this->image($data['pre_implementation']['image'], $rootReportId) : null,
            ) : null,
            'photographicDocumentation' => isset($data['photographic_documentation']) ? new ReportPhotographicDocumentationValueObject(
                images: array_map(fn (array $image): ReportImageEntity => $this->image($image, $rootReportId), $data['photographic_documentation']['images'] ?? []),
            ) : null,
            'conclusion' => isset($data['conclusion']) ? new ReportConclusionValueObject($data['conclusion']['content'] ?? '') : null,
        ];
    }

    /** @param CoverInput $data */
    private function cover(array $data): ReportCoverValueObject
    {
        $municipality = isset($data['municipality_id']) ? DB::table('municipalities')
            ->where('id', $data['municipality_id'])->where('state_code', 'BA')->where('is_active', true)->first() : null;

        if (isset($data['municipality_id']) && $municipality === null) {
            throw new InvalidMunicipalityException;
        }

        return new ReportCoverValueObject(
            municipality: $municipality === null ? null : new MunicipalityValueObject((int) $municipality->id, $municipality->name, $municipality->state_code),
            force: isset($data['force']) ? ForceEnum::from($data['force']) : null,
            size: isset($data['size']) ? ReportSizeEnum::from($data['size']) : null,
            typology: $data['typology'] ?? '',
            seiNumber: isset($data['sei_number']) ? new SeiNumberValueObject($data['sei_number']) : null,
        );
    }

    /** @param array<string, string|null> $data
     * @return array<string, ChecklistAnswerEnum|null>
     */
    private function answers(array $data): array
    {
        $answers = [];
        foreach ($data as $field => $answer) {
            $answers[Str::camel($field)] = $answer === null ? null : ChecklistAnswerEnum::from($answer);
        }

        return $answers;
    }

    /** @param ImageInput $data */
    private function image(array $data, ?string $rootReportId): ReportImageEntity
    {
        return new ReportImageEntity(
            file: $this->file($data['file'], $rootReportId),
            order: (int) $data['order'],
            caption: $data['caption'] ?? '',
            id: $data['id'] ?? null,
        );
    }

    /** @param FileInput $data */
    private function file(array $data, ?string $rootReportId): ReportFileReferenceValueObject
    {
        $path = $data['storage_identifier'];
        $prefix = 'reports/'.$rootReportId.'/media/';
        if ($rootReportId === null || ! str_starts_with($path, $prefix)
            || ! preg_match('/^[a-f0-9-]{36}\\.(png|jpg|pdf)$/i', substr($path, strlen($prefix)))) {
            throw new InvalidReportFileReferenceException('O arquivo deve pertencer à família deste relatório.');
        }

        $disk = Storage::disk('local');
        $checksum = $disk->exists($path) ? hash_file('sha256', $disk->path($path)) : false;
        if (! $disk->exists($path)
            || $disk->size($path) !== (int) $data['size_bytes']
            || $disk->mimeType($path) !== $data['mime_type']
            || ! is_string($checksum)
            || ! hash_equals(strtolower($data['checksum']), $checksum)) {
            throw new InvalidReportFileReferenceException('Os metadados não correspondem ao arquivo recebido.');
        }

        return new ReportFileReferenceValueObject(
            storageIdentifier: $path,
            fileName: $data['file_name'],
            mimeType: $data['mime_type'],
            sizeBytes: (int) $data['size_bytes'],
            checksum: $data['checksum'],
        );
    }

    /** @param AttachmentsInput $data */
    private function attachments(array $data, string $rootReportId): ReportAttachmentsValueObject
    {
        return new ReportAttachmentsValueObject(
            checklist: isset($data['checklist']) ? new ReportChecklistValueObject(...$this->answers($data['checklist'])) : null,
            municipalityLocationMap: isset($data['municipality_location_map']) ? $this->attachment($data['municipality_location_map'], ReportAttachmentTypeEnum::MUNICIPALITY_LOCATION_MAP, $rootReportId) : null,
            topographicPlan: isset($data['topographic_plan']) ? $this->attachment($data['topographic_plan'], ReportAttachmentTypeEnum::TOPOGRAPHIC_PLAN, $rootReportId) : null,
            others: array_map(fn (array $attachment): ReportAttachmentEntity => $this->attachment($attachment, ReportAttachmentTypeEnum::OTHER, $rootReportId), $data['others'] ?? []),
        );
    }

    /** @param AttachmentInput $data */
    private function attachment(array $data, ReportAttachmentTypeEnum $type, string $rootReportId): ReportAttachmentEntity
    {
        return new ReportAttachmentEntity(
            type: $type,
            file: $this->file($data['file'], $rootReportId),
            description: $data['description'] ?? '',
            id: $data['id'] ?? null,
        );
    }
}
