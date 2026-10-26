<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Infra\Service;

use Illuminate\Support\Facades\DB;
use src\Modules\Attachment\Application\DTO\GetMediaReportInputDTO;
use src\Modules\Attachment\Application\DTO\GetMediaReportOutputDTO;
use src\Modules\Attachment\Application\Exception\ReportMediaUnavailableException;
use src\Modules\Attachment\Application\Interfaces\Service\ReportMediaRetrievalServiceInterface;
use src\Modules\Attachment\Infra\Trait\ResolvesEditorialReportMediaTrait;
use src\Modules\Attachment\Infra\Trait\ResolvesFamilyReportMediaTrait;
use src\Modules\Attachment\Infra\Trait\ResolvesReportMediaReferenceTrait;
use src\Modules\Attachment\Infra\Trait\StreamsReportMediaFileTrait;
use src\Modules\Attachment\Infra\Trait\ValidatesReportMediaFileTrait;
use src\Modules\Report\Application\Exception\ReportAccessDeniedException;
use src\Modules\Report\Application\Exception\ReportNotFoundException;
use src\Modules\Report\Application\Interfaces\Service\ReportAccessServiceInterface;
use src\Modules\Report\Domain\Repository\ReportRepositoryInterface;
use src\Modules\Report\Model\Report;

class ReportMediaRetrievalService implements ReportMediaRetrievalServiceInterface
{
    use ResolvesEditorialReportMediaTrait;
    use ResolvesFamilyReportMediaTrait;
    use ResolvesReportMediaReferenceTrait;
    use StreamsReportMediaFileTrait;
    use ValidatesReportMediaFileTrait;

    public function __construct(
        private readonly ReportRepositoryInterface $reports,
        private readonly ReportAccessServiceInterface $access,
    ) {}

    public function get(GetMediaReportInputDTO $input): GetMediaReportOutputDTO
    {
        $model = Report::query()->whereRaw('LOWER(uuid) = ?', [strtolower($input->reportId)])->first();

        if ($model === null) {
            throw new ReportNotFoundException;
        }

        if (! $this->access->canRead($input->requestedBy, $model->uuid)) {
            throw new ReportAccessDeniedException;
        }

        $rootReportId = DB::table('report_series')->where('id', $model->report_series_id)->value('uuid');
        $report = $this->findReport($model->uuid);

        if (! is_string($rootReportId) || $report === null) {
            throw new ReportNotFoundException;
        }

        $mediaId = strtolower($input->mediaId);
        $file = $this->findReference($report, $mediaId, $this->access->canWrite($input->requestedBy, $model->uuid))
            ?? $this->findFamilyReference($input->requestedBy, $model, $mediaId)
            ?? $this->findEditorialUpload($input->requestedBy, $model, $rootReportId, $mediaId);

        if ($file === null) {
            throw new ReportMediaUnavailableException('O arquivo do relatório não foi encontrado.', 404);
        }

        $mimeType = $this->mediaMimeType($file, $rootReportId);

        return $this->open($file, $this->downloadFileName($file), $mimeType);
    }
}
