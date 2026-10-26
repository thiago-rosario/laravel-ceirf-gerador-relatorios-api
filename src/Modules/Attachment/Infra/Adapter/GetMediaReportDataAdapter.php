<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Infra\Adapter;

use src\Modules\Attachment\Application\DTO\GetMediaReportInputDTO;
use src\Modules\Attachment\Application\Interfaces\Adapter\GetMediaReportDataAdapterInterface;

class GetMediaReportDataAdapter implements GetMediaReportDataAdapterInterface
{
    /** @param array{id: string, mediaId: string, requested_by: string, disposition?: string} $data */
    public function fromArray(array $data): GetMediaReportInputDTO
    {
        return new GetMediaReportInputDTO(
            reportId: strtolower($data['id']),
            mediaId: strtolower($data['mediaId']),
            requestedBy: $data['requested_by'],
            disposition: $data['disposition'] ?? 'inline',
        );
    }
}
