<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\DTO;

readonly class ReportPdfFileDTO
{
    /** @param resource $stream */
    public function __construct(
        public mixed $stream,
        public string $fileName,
        public int $sizeBytes,
    ) {}

    public function __destruct()
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }
}
