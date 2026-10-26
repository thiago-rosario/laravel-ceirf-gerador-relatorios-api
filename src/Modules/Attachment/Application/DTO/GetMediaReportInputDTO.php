<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Application\DTO;

readonly class GetMediaReportInputDTO
{
    public function __construct(
        public string $reportId,
        public string $mediaId,
        public string $requestedBy,
        public string $disposition = 'inline',
    ) {}
}
