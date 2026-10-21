<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Infra\Service;

use Illuminate\Support\Facades\DB;
use src\Modules\Attachment\Application\Interfaces\Service\ReportMediaRegistryServiceInterface;
use src\Modules\Report\Domain\Entity\ReportEntity;
use src\Modules\Report\Domain\Validation\ReportMediaValidation;
use src\Modules\Report\Domain\ValueObject\ReportFileReferenceValueObject;

class ReportMediaRegistryService implements ReportMediaRegistryServiceInterface
{
    public function markLinked(ReportEntity $report): void
    {
        $paths = array_map(
            fn (ReportFileReferenceValueObject $file): string => $file->storageIdentifier(),
            ReportMediaValidation::files($report),
        );

        if ($paths === []) {
            return;
        }

        DB::table('report_media_uploads')
            ->where('root_report_id', $report->rootReportId()->value())
            ->whereIn('storage_identifier', $paths)
            ->whereNull('linked_at')
            ->update(['linked_at' => now()]);
    }
}
