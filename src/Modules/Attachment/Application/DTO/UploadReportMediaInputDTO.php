<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Application\DTO;

use Illuminate\Http\UploadedFile;

readonly class UploadReportMediaInputDTO
{
    public function __construct(
        public string $reportId,
        public int $uploadedBy,
        public UploadedFile $file,
        public ?string $checksum = null,
        public ?string $replacesId = null,
    ) {}
}
