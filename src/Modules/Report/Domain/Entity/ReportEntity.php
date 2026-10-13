<?php

declare(strict_types=1);

namespace src\Modules\Report\Domain\Entity;

use DateTimeImmutable;
use DateTimeInterface;
use src\Modules\Report\Application\Interfaces\Mapper\LegacyReportQueryMapperInterface;
use src\Modules\Report\Application\Interfaces\Mapper\ReportPersistenceMapperInterface;
use src\Modules\Report\Domain\Enum\ReportStatusEnum;
use src\Modules\Report\Domain\Exception\InvalidGeneratedReportException;
use src\Modules\Report\Domain\Exception\InvalidReportRevisionException;
use src\Modules\Report\Domain\Exception\ReportNotGeneratedException;
use src\Modules\Report\Domain\Service\ReportFileNameService;
use src\Modules\Report\Domain\Trait\ReportEditingTrait;
use src\Modules\Report\Domain\Trait\ResolvesReportValuesTrait;
use src\Modules\Report\Domain\Validation\ReportValidation;
use src\Modules\Report\Domain\ValueObject\GeneratedReportValueObject;
use src\Modules\Report\Domain\ValueObject\ReportAttachmentsValueObject;
use src\Modules\Report\Domain\ValueObject\ReportConclusionValueObject;
use src\Modules\Report\Domain\ValueObject\ReportCoverValueObject;
use src\Modules\Report\Domain\ValueObject\ReportGeneralInformationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportInfrastructureValueObject;
use src\Modules\Report\Domain\ValueObject\ReportLocationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPhotographicDocumentationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPreImplementationValueObject;
use src\Modules\Report\Model\Report as ReportModel;
use src\Modules\Shared\Resolver\UuidResolver;

/**
 * Raiz do agregado de uma versão documental. Seções imutáveis mantêm snapshots de conteúdo.
 */
class ReportEntity
{
    use ReportEditingTrait;
    use ResolvesReportValuesTrait;

    private UuidResolver $id;

    private UuidResolver $rootReportId;

    private ?UuidResolver $parentReportId;

    private UuidResolver $createdBy;

    private ReportStatusEnum $status;

    private DateTimeImmutable $createdAt;

    private DateTimeImmutable $updatedAt;

    /**
     * O histórico de uploads deve ser restaurado pelo repositório junto ao conteúdo atual.
     *
     * @param  list<ReportImageEntity>  $uploadedImages
     */
    public function __construct(
        UuidResolver|string $createdBy,
        UuidResolver|string|null $id = null,
        UuidResolver|string|null $rootReportId = null,
        UuidResolver|string|null $parentReportId = null,
        private int $revisionNumber = 0,
        ?ReportStatusEnum $status = null,
        private ?ReportCoverValueObject $cover = null,
        private ?ReportGeneralInformationValueObject $generalInformation = null,
        private ?ReportLocationValueObject $location = null,
        private ?ReportInfrastructureValueObject $infrastructure = null,
        private ?ReportPreImplementationValueObject $preImplementation = null,
        private ?ReportPhotographicDocumentationValueObject $photographicDocumentation = null,
        private ?ReportAttachmentsValueObject $attachments = null,
        private ?ReportConclusionValueObject $conclusion = null,
        private ?GeneratedReportValueObject $generatedDocument = null,
        DateTimeInterface|string|null $createdAt = null,
        DateTimeInterface|string|null $updatedAt = null,
        private array $uploadedImages = [],
        private ?int $coordinationId = null,
        private ?int $reportTypeId = null,
    ) {
        $this->id = $this->resolveUuid($id);
        $this->rootReportId = $rootReportId === null ? $this->id : $this->resolveUuid($rootReportId);
        $this->parentReportId = $parentReportId === null ? null : $this->resolveUuid($parentReportId);
        $this->createdBy = $this->resolveUuid($createdBy);
        $this->status = $status ?? ($generatedDocument === null ? ReportStatusEnum::DRAFT : ReportStatusEnum::GENERATED);
        $this->createdAt = $this->resolveDateTime($createdAt);
        $this->updatedAt = $this->resolveDateTime($updatedAt, $this->createdAt);
        $this->validate();

        if ($this->isGenerated()) {
            $this->validateForGeneration();
        }

        $this->rememberUploadedImages();
    }

    public function id(): UuidResolver
    {
        return $this->id;
    }

    public function rootReportId(): UuidResolver
    {
        return $this->rootReportId;
    }

    public function parentReportId(): ?UuidResolver
    {
        return $this->parentReportId;
    }

    public function createdBy(): UuidResolver
    {
        return $this->createdBy;
    }

    public function revisionNumber(): int
    {
        return $this->revisionNumber;
    }

    public function status(): ReportStatusEnum
    {
        return $this->status;
    }

    public function coordinationId(): ?int
    {
        return $this->coordinationId;
    }

    public function reportTypeId(): ?int
    {
        return $this->reportTypeId;
    }

    public function cover(): ?ReportCoverValueObject
    {
        return $this->cover;
    }

    public function generalInformation(): ?ReportGeneralInformationValueObject
    {
        return $this->generalInformation;
    }

    public function location(): ?ReportLocationValueObject
    {
        return $this->location;
    }

    public function infrastructure(): ?ReportInfrastructureValueObject
    {
        return $this->infrastructure;
    }

    public function preImplementation(): ?ReportPreImplementationValueObject
    {
        return $this->preImplementation;
    }

    public function photographicDocumentation(): ?ReportPhotographicDocumentationValueObject
    {
        return $this->photographicDocumentation;
    }

    public function attachments(): ?ReportAttachmentsValueObject
    {
        return $this->attachments;
    }

    public function conclusion(): ?ReportConclusionValueObject
    {
        return $this->conclusion;
    }

    public function generatedDocument(): ?GeneratedReportValueObject
    {
        return $this->generatedDocument;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return list<ReportImageEntity> */
    public function uploadedImages(): array
    {
        return $this->uploadedImages;
    }

    public function changeCover(ReportCoverValueObject $cover): void
    {
        $this->ensureEditable();
        $candidate = clone $this;
        $candidate->cover = $cover;
        $candidate->validate();

        $this->cover = $cover;
        $this->rememberUploadedImages();
        $this->touch();
    }

    public function changeGeneralInformation(ReportGeneralInformationValueObject $generalInformation): void
    {
        $this->ensureEditable();
        $candidate = clone $this;
        $candidate->generalInformation = $generalInformation;
        $candidate->validate();

        $this->generalInformation = $generalInformation;
        $this->rememberUploadedImages();
        $this->touch();
    }

    public function changeLocation(ReportLocationValueObject $location): void
    {
        $this->ensureEditable();
        $candidate = clone $this;
        $candidate->location = $location;
        $candidate->validate();

        $this->location = $location;
        $this->rememberUploadedImages();
        $this->touch();
    }

    public function changeInfrastructure(ReportInfrastructureValueObject $infrastructure): void
    {
        $this->ensureEditable();
        $candidate = clone $this;
        $candidate->infrastructure = $infrastructure;
        $candidate->validate();

        $this->infrastructure = $infrastructure;
        $this->rememberUploadedImages();
        $this->touch();
    }

    public function changePreImplementation(ReportPreImplementationValueObject $preImplementation): void
    {
        $this->ensureEditable();
        $candidate = clone $this;
        $candidate->preImplementation = $preImplementation;
        $candidate->validate();

        $this->preImplementation = $preImplementation;
        $this->rememberUploadedImages();
        $this->touch();
    }

    public function changePhotographicDocumentation(ReportPhotographicDocumentationValueObject $photographicDocumentation): void
    {
        $this->ensureEditable();
        $candidate = clone $this;
        $candidate->photographicDocumentation = $photographicDocumentation;
        $candidate->validate();

        $this->photographicDocumentation = $photographicDocumentation;
        $this->rememberUploadedImages();
        $this->touch();
    }

    public function changeAttachments(ReportAttachmentsValueObject $attachments): void
    {
        $this->ensureEditable();
        $candidate = clone $this;
        $candidate->attachments = $attachments;
        $candidate->validate();

        $this->attachments = $attachments;
        $this->rememberUploadedImages();
        $this->touch();
    }

    public function changeConclusion(ReportConclusionValueObject $conclusion): void
    {
        $this->ensureEditable();
        $candidate = clone $this;
        $candidate->conclusion = $conclusion;
        $candidate->validate();

        $this->conclusion = $conclusion;
        $this->rememberUploadedImages();
        $this->touch();
    }

    /**
     * Registra a referência apenas depois de a infraestrutura concluir a geração efetiva.
     */
    public function registerGeneratedDocument(GeneratedReportValueObject $document): void
    {
        $this->ensureEditable();
        $this->validateForGeneration();

        if ($document->fileName() !== $this->fileName()) {
            throw new InvalidGeneratedReportException;
        }

        $this->generatedDocument = $document;
        $this->status = ReportStatusEnum::GENERATED;
        $this->touch();
    }

    public function isGenerated(): bool
    {
        return $this->generatedDocument !== null;
    }

    public function isRevision(): bool
    {
        return $this->revisionNumber > 0;
    }

    public function revisionLabel(): ?string
    {
        return $this->isRevision() ? sprintf('REV%d', $this->revisionNumber) : null;
    }

    public function fileName(): string
    {
        return ReportFileNameService::compose($this);
    }

    /**
     * Copia snapshots imutáveis para uma nova versão, preservando a versão e o PDF anteriores.
     */
    public function createRevision(UuidResolver|string|null $createdBy = null): self
    {
        if (! $this->isGenerated()) {
            throw new ReportNotGeneratedException;
        }

        if ($this->revisionNumber === PHP_INT_MAX) {
            throw new InvalidReportRevisionException;
        }

        return new self(
            createdBy: $createdBy ?? $this->createdBy,
            rootReportId: $this->rootReportId,
            parentReportId: $this->id,
            revisionNumber: $this->revisionNumber + 1,
            cover: $this->cover,
            generalInformation: $this->generalInformation,
            location: $this->location,
            infrastructure: $this->infrastructure,
            preImplementation: $this->preImplementation,
            photographicDocumentation: $this->photographicDocumentation,
            attachments: $this->attachments,
            conclusion: $this->conclusion,
            uploadedImages: $this->uploadedImages,
            coordinationId: $this->coordinationId,
            reportTypeId: $this->reportTypeId,
        );
    }

    public function validate(): void
    {
        ReportValidation::validate($this);
    }

    public function validateForGeneration(): void
    {
        ReportValidation::validateForGeneration($this);
    }

    public static function fromModel(
        ReportModel $model,
        ReportPersistenceMapperInterface $mapper,
        LegacyReportQueryMapperInterface $legacyMapper,
    ): self {
        if ($model->payload === null) {
            return $legacyMapper->fromModel($model);
        }

        $payload = $model->payload;

        if ($model->exists) {
            $payload['coordinationId'] = $model->coordination_id;
            $payload['reportTypeId'] = $model->report_type_id;
        }

        return $mapper->fromPayload($payload);
    }
}
