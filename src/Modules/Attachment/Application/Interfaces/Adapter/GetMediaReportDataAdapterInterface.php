<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Application\Interfaces\Adapter;

use src\Modules\Attachment\Application\DTO\GetMediaReportInputDTO;

interface GetMediaReportDataAdapterInterface
{
    /** @param array{id: string, mediaId: string, requested_by: string, disposition?: string} $data */
    public function fromArray(array $data): GetMediaReportInputDTO;
}
