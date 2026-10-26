<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Infra\Trait;

use Illuminate\Support\Facades\DB;
use src\Modules\Attachment\Application\Exception\ReportMediaUnavailableException;
use src\Modules\Report\Domain\Exception\InvalidReportFileReferenceException;
use src\Modules\Report\Domain\ValueObject\ReportFileReferenceValueObject;
use src\Modules\Report\Model\Report;

trait ResolvesEditorialReportMediaTrait
{
    private function findEditorialUpload(string $userId, Report $requestedReport, string $rootReportId, string $mediaId): ?ReportFileReferenceValueObject
    {
        $upload = DB::table('report_media_uploads')->join('reports', 'reports.id', '=', 'report_media_uploads.report_id')
            ->where('reports.report_series_id', $requestedReport->report_series_id)
            ->where('report_media_uploads.root_report_id', $rootReportId)
            ->whereRaw('LOWER(report_media_uploads.uuid) = ?', [$mediaId])
            ->first(['report_media_uploads.*', 'reports.uuid as report_uuid']);

        if ($upload === null || ! $this->access->canWrite($userId, (string) $upload->report_uuid)) {
            return null;
        }

        try {
            return new ReportFileReferenceValueObject(
                storageIdentifier: (string) $upload->storage_identifier,
                fileName: (string) $upload->file_name,
                mimeType: (string) $upload->mime_type,
                sizeBytes: (int) $upload->size_bytes,
                checksum: (string) $upload->checksum,
            );
        } catch (InvalidReportFileReferenceException $exception) {
            throw new ReportMediaUnavailableException('Os metadados do arquivo do relatório são inválidos.', 422, $exception);
        }
    }
}
