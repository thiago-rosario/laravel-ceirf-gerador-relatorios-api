<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Infra\Adapter;

use Illuminate\Http\UploadedFile;
use src\Modules\Attachment\Application\DTO\UploadReportMediaInputDTO;
use src\Modules\Attachment\Application\DTO\UploadReportMediaOutputDTO;
use src\Modules\Attachment\Application\Interfaces\Adapter\UploadReportMediaDataAdapterInterface;

class UploadReportMediaDataAdapter implements UploadReportMediaDataAdapterInterface
{
    /**
     * @param  array{id: string, uploaded_by: int, file: UploadedFile, checksum?: string, replaces_id?: string}  $data
     */
    public function fromArray(array $data): UploadReportMediaInputDTO
    {
        return new UploadReportMediaInputDTO(
            reportId: $data['id'],
            uploadedBy: $data['uploaded_by'],
            file: $data['file'],
            checksum: $data['checksum'] ?? null,
            replacesId: $data['replaces_id'] ?? null,
        );
    }

    /**
     * @return array{id: string, root_report_id: string, file: array{storage_identifier: string, file_name: string, mime_type: string, size_bytes: int, checksum: string}}
     */
    public function toArray(UploadReportMediaOutputDTO $data): array
    {
        return [
            'id' => $data->id,
            'root_report_id' => $data->rootReportId,
            'file' => [
                'storage_identifier' => $data->file->storageIdentifier,
                'file_name' => $data->file->fileName,
                'mime_type' => $data->file->mimeType,
                'size_bytes' => $data->file->sizeBytes,
                'checksum' => $data->file->checksum,
            ],
        ];
    }
}
