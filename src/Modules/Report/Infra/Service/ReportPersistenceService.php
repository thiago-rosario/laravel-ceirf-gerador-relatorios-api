<?php

declare(strict_types=1);

namespace src\Modules\Report\Infra\Service;

use Illuminate\Support\Facades\DB;
use src\Modules\Attachment\Application\Interfaces\Service\ReportMediaRegistryServiceInterface;
use src\Modules\Identity\Model\User;
use src\Modules\Report\Application\Interfaces\Mapper\ReportPersistenceMapperInterface;
use src\Modules\Report\Application\Interfaces\Service\ReportPersistenceServiceInterface;
use src\Modules\Report\Domain\Entity\ReportEntity;
use src\Modules\Report\Domain\Entity\ReportImageEntity;
use src\Modules\Report\Domain\Enum\ForceEnum;
use src\Modules\Report\Domain\Validation\ReportMediaValidation;

class ReportPersistenceService implements ReportPersistenceServiceInterface
{
    public function __construct(
        private readonly ReportPersistenceMapperInterface $mapper,
        private readonly ReportMediaRegistryServiceInterface $mediaRegistry,
    ) {}

    public function authorId(ReportEntity $report): int
    {
        return User::query()->where('uuid', $report->createdBy()->value())->firstOrFail()->id;
    }

    /** @return array<string, mixed> */
    public function attributes(ReportEntity $report): array
    {
        $cover = $report->cover();
        $forceCode = $cover?->force() === ForceEnum::CBM ? 'BM' : $cover?->force()?->value;
        $forceId = $forceCode === null ? null : DB::table('forces')->where('code', $forceCode)->value('id');
        $sizeName = $cover?->size()?->value;
        $sizeId = $sizeName === null ? null : DB::table('sizes')->whereRaw('LOWER(name) = ?', [mb_strtolower($sizeName)])->value('id');

        return [
            ...$this->mapper->toAttributes($report),
            'uuid' => $report->id()->value(),
            'revision_number' => $report->revisionNumber(),
            'coordination_id' => $report->coordinationId(),
            'report_type_id' => $report->reportTypeId(),
            'municipality_id' => $cover?->municipality()?->id(),
            'force_id' => $forceId,
            'size_id' => $sizeId,
            'status' => $report->status(),
            'payload' => $this->mapper->toPayload($report),
            'created_at' => $report->createdAt(),
            'updated_at' => $report->updatedAt(),
        ];
    }

    /** @param list<ReportImageEntity> $previousImages */
    public function preserveUploadedImages(ReportEntity $report, array $previousImages): ReportEntity
    {
        ReportMediaValidation::validateReplacement($previousImages, $report->uploadedImages());
        ReportMediaValidation::validateReplacement($previousImages, ReportMediaValidation::images($report));
        $history = [];

        foreach ([...$previousImages, ...$report->uploadedImages()] as $image) {
            $history[strtolower($image->id()->value())] ??= $image;
        }

        return new ReportEntity(
            id: $report->id(),
            rootReportId: $report->rootReportId(),
            parentReportId: $report->parentReportId(),
            createdBy: $report->createdBy(),
            revisionNumber: $report->revisionNumber(),
            status: $report->status(),
            cover: $report->cover(),
            generalInformation: $report->generalInformation(),
            location: $report->location(),
            infrastructure: $report->infrastructure(),
            preImplementation: $report->preImplementation(),
            photographicDocumentation: $report->photographicDocumentation(),
            attachments: $report->attachments(),
            conclusion: $report->conclusion(),
            generatedDocument: $report->generatedDocument(),
            createdAt: $report->createdAt(),
            updatedAt: $report->updatedAt(),
            uploadedImages: array_values($history),
            coordinationId: $report->coordinationId(),
            reportTypeId: $report->reportTypeId(),
        );
    }

    public function storeMedia(ReportEntity $report, int $reportId, int $authorId, ?int $parentReportId = null): void
    {
        $this->mediaRegistry->markLinked($report);

        $inheritedImages = $parentReportId === null ? collect() : DB::table('report_images')->where('report_id', $parentReportId)->get()->keyBy('file_hash');
        $inheritedAttachments = $parentReportId === null ? collect() : DB::table('report_attachments')->where('report_id', $parentReportId)->get()->keyBy('file_hash');
        $activeImages = [];

        foreach (ReportMediaValidation::images($report) as $image) {
            $activeImages[strtolower($image->id()->value())] = $image;
        }

        foreach ($report->uploadedImages() as $uploadedImage) {
            $image = $activeImages[strtolower($uploadedImage->id()->value())] ?? $uploadedImage;
            $file = $image->file();
            $identity = ['report_id' => $reportId, 'file_hash' => $file->checksum()];

            if (DB::table('report_images')->where($identity)->exists()) {
                DB::table('report_images')->where($identity)->update([
                    'caption' => $image->caption(),
                    'updated_at' => $report->updatedAt(),
                ]);

                continue;
            }

            DB::table('report_images')->insert([
                ...$identity,
                'uuid' => $image->id()->value(),
                'uploaded_by' => $inheritedImages->get($file->checksum())->uploaded_by ?? $authorId,
                'type' => 'figure',
                'original_name' => $file->fileName(),
                'storage_path' => $file->storageIdentifier(),
                'mime_type' => $file->mimeType(),
                'size_bytes' => $file->sizeBytes(),
                'caption' => $image->caption(),
                'position' => $image->order(),
                'created_at' => $inheritedImages->get($file->checksum())->created_at ?? $report->updatedAt(),
                'updated_at' => $report->updatedAt(),
            ]);
        }

        $existingAttachments = DB::table('report_attachments')->where('report_id', $reportId)->get()->keyBy('file_hash');
        DB::table('report_attachments')->where('report_id', $reportId)->delete();

        foreach ($report->attachments()?->files() ?? [] as $attachment) {
            $file = $attachment->file();
            $previousUpload = $existingAttachments->get($file->checksum()) ?? $inheritedAttachments->get($file->checksum());
            DB::table('report_attachments')->insert([
                'report_id' => $reportId,
                'file_hash' => $file->checksum(),
                'uuid' => $attachment->id()->value(),
                'uploaded_by' => $previousUpload->uploaded_by ?? $authorId,
                'type' => $attachment->type()->value,
                'original_name' => $file->fileName(),
                'storage_path' => $file->storageIdentifier(),
                'mime_type' => $file->mimeType(),
                'size_bytes' => $file->sizeBytes(),
                'description' => $attachment->description(),
                'created_at' => $previousUpload->created_at ?? $report->updatedAt(),
                'updated_at' => $report->updatedAt(),
            ]);
        }
    }
}
