<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Application\DTO;

readonly class GetMediaReportOutputDTO
{
    /** @param resource $stream */
    public function __construct(
        public mixed $stream,
        public string $fileName,
        public string $mimeType,
        public int $sizeBytes,
    ) {}

    public function __destruct()
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }
}
