<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Application\Interfaces\Adapter;

use Illuminate\Http\UploadedFile;
use src\Modules\Attachment\Application\DTO\UploadReportMediaInputDTO;
use src\Modules\Attachment\Application\DTO\UploadReportMediaOutputDTO;

interface UploadReportMediaDataAdapterInterface
{
    /**
     * @param  array{id: string, uploaded_by: int, file: UploadedFile, checksum?: string, replaces_id?: string}  $data
     */
    public function fromArray(array $data): UploadReportMediaInputDTO;

    /**
     * @return array{id: string, root_report_id: string, file: array{storage_identifier: string, file_name: string, mime_type: string, size_bytes: int, checksum: string}}
     */
    public function toArray(UploadReportMediaOutputDTO $data): array;
}
