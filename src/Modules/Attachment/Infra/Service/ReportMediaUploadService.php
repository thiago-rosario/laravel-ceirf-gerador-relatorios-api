<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Infra\Service;

use finfo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use src\Modules\Attachment\Application\DTO\UploadReportMediaInputDTO;
use src\Modules\Attachment\Application\DTO\UploadReportMediaOutputDTO;
use src\Modules\Attachment\Application\Interfaces\Service\ReportMediaRegistryServiceInterface;
use src\Modules\Attachment\Application\Interfaces\Service\ReportMediaUploadServiceInterface;
use src\Modules\Report\Application\DTO\ReportFileReferenceDataDTO;
use src\Modules\Report\Application\Exception\ReportNotFoundException;
use src\Modules\Report\Domain\Entity\ReportEntity;
use src\Modules\Report\Domain\Enum\ReportStatusEnum;
use src\Modules\Report\Domain\Exception\DuplicateReportFileException;
use src\Modules\Report\Domain\Exception\InvalidReportFileReferenceException;
use src\Modules\Report\Domain\Exception\InvalidReportImageFormatException;
use src\Modules\Report\Domain\Exception\ReportAlreadyGeneratedException;
use src\Modules\Report\Domain\Exception\TooManyReportFiguresException;
use src\Modules\Report\Domain\Repository\ReportRepositoryInterface;
use src\Modules\Report\Domain\Validation\ReportImageValidation;
use src\Modules\Report\Domain\Validation\ReportMediaValidation;
use src\Modules\Report\Domain\ValueObject\ReportFileReferenceValueObject;
use src\Modules\Report\Model\Report;
use Throwable;

class ReportMediaUploadService implements ReportMediaUploadServiceInterface
{
    public function __construct(
        private readonly ReportRepositoryInterface $reports,
        private readonly ReportMediaRegistryServiceInterface $registry,
    ) {}

    public function upload(UploadReportMediaInputDTO $input): UploadReportMediaOutputDTO
    {
        $disk = Storage::disk('local');
        $storedPath = null;

        try {
            return DB::transaction(function () use ($input, $disk, &$storedPath): UploadReportMediaOutputDTO {
                $model = Report::query()->where('uuid', $input->reportId)->lockForUpdate()->first();

                if ($model === null) {
                    throw new ReportNotFoundException;
                }

                if ($model->status === ReportStatusEnum::GENERATED) {
                    throw new ReportAlreadyGeneratedException;
                }

                $rootReportId = DB::table('report_series')->where('id', $model->report_series_id)
                    ->lockForUpdate()->value('uuid');
                $report = $this->reports->findById($input->reportId);

                if (! is_string($rootReportId) || $report === null) {
                    throw new ReportNotFoundException;
                }

                $id = (string) Str::uuid();
                $file = $this->fileReference($input, $rootReportId, $id);
                $this->validateDuplicate($rootReportId, $model->report_series_id, $file->checksum());
                $this->registry->markLinked($report);
                $this->validateCapacity($report, $model->id, $input->replacesId);

                $storedPath = $file->storageIdentifier();
                $result = $disk->putFileAs(dirname($storedPath), $input->file, basename($storedPath), ['visibility' => 'private']);

                if ($result === false) {
                    throw new RuntimeException('Não foi possível armazenar o arquivo enviado.');
                }

                DB::table('report_media_uploads')->insert([
                    'uuid' => $id,
                    'report_id' => $model->id,
                    'root_report_id' => $rootReportId,
                    'uploaded_by' => $input->uploadedBy,
                    'file_name' => $file->fileName(),
                    'storage_identifier' => $storedPath,
                    'mime_type' => $file->mimeType(),
                    'size_bytes' => $file->sizeBytes(),
                    'checksum' => $file->checksum(),
                    'replaces_id' => $input->replacesId === null ? null : strtolower($input->replacesId),
                    'created_at' => now(),
                ]);

                return new UploadReportMediaOutputDTO(
                    id: $id,
                    rootReportId: $rootReportId,
                    file: new ReportFileReferenceDataDTO(
                        storageIdentifier: $storedPath,
                        fileName: $file->fileName(),
                        mimeType: $file->mimeType(),
                        sizeBytes: $file->sizeBytes(),
                        checksum: $file->checksum(),
                    ),
                );
            });
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                try {
                    $disk->delete($storedPath);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw $exception;
        }
    }

    private function fileReference(UploadReportMediaInputDTO $input, string $rootReportId, string $id): ReportFileReferenceValueObject
    {
        $path = $input->file->getRealPath();

        if (! $input->file->isValid() || $path === false) {
            throw new InvalidReportFileReferenceException('O arquivo enviado não é válido.');
        }

        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        $extension = match ($mimeType) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'application/pdf' => 'pdf',
            default => throw new InvalidReportImageFormatException,
        };
        $size = filesize($path);
        $checksum = hash_file('sha256', $path);
        $fileName = basename(str_replace('\\', '/', $input->file->getClientOriginalName()));

        if ($size === false || $checksum === false || mb_strlen($fileName) > 255
            || preg_match('/[\x00-\x1F\x7F]/', $fileName) === 1) {
            throw new InvalidReportFileReferenceException;
        }

        if ($input->checksum !== null && ! hash_equals($checksum, strtolower($input->checksum))) {
            throw new InvalidReportFileReferenceException('O checksum SHA-256 não corresponde ao arquivo enviado.');
        }

        $file = new ReportFileReferenceValueObject(
            storageIdentifier: 'reports/'.$rootReportId.'/media/'.$id.'.'.$extension,
            fileName: $fileName,
            mimeType: $mimeType,
            sizeBytes: $size,
            checksum: $checksum,
        );
        ReportImageValidation::validateFile($file);

        return $file;
    }

    private function validateDuplicate(string $rootReportId, int $seriesId, string $checksum): void
    {
        if (DB::table('report_media_uploads')->where('root_report_id', $rootReportId)->where('checksum', $checksum)->exists()) {
            throw new DuplicateReportFileException;
        }

        foreach (['report_images', 'report_attachments'] as $table) {
            if (DB::table($table)->join('reports', 'reports.id', '=', $table.'.report_id')
                ->where('reports.report_series_id', $seriesId)->where($table.'.file_hash', $checksum)->exists()) {
                throw new DuplicateReportFileException;
            }
        }
    }

    private function validateCapacity(ReportEntity $report, int $reportId, ?string $replacesId): void
    {
        $media = [...ReportMediaValidation::images($report), ...($report->attachments()?->files() ?? [])];
        $activeIds = [];

        foreach ($media as $item) {
            $activeIds[strtolower($item->id()->value())] = true;
        }

        $replacesId = $replacesId === null ? null : strtolower($replacesId);

        if ($replacesId !== null && ! isset($activeIds[$replacesId])) {
            throw new InvalidReportFileReferenceException('A mídia a substituir deve estar vinculada a este relatório.');
        }

        $pending = 0;
        $reservedIds = [];

        foreach (DB::table('report_media_uploads')->where('report_id', $reportId)->whereNull('linked_at')->get(['replaces_id']) as $upload) {
            if ($replacesId !== null && $upload->replaces_id === $replacesId) {
                throw new InvalidReportFileReferenceException('Já existe um upload pendente para substituir esta mídia.');
            }

            if ($upload->replaces_id !== null && isset($activeIds[$upload->replaces_id]) && ! isset($reservedIds[$upload->replaces_id])) {
                $reservedIds[$upload->replaces_id] = true;

                continue;
            }

            $pending++;
        }

        if (count($media) + $pending + ($replacesId === null ? 1 : 0) > ReportMediaValidation::MAXIMUM_FIGURES) {
            throw new TooManyReportFiguresException;
        }
    }
}
