<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Infra\Trait;

use Illuminate\Support\Facades\DB;
use src\Modules\Report\Domain\ValueObject\ReportFileReferenceValueObject;
use src\Modules\Report\Model\Report;

trait ResolvesFamilyReportMediaTrait
{
    private function findFamilyReference(string $userId, Report $requestedReport, string $mediaId): ?ReportFileReferenceValueObject
    {
        $legacyReportIds = [];

        foreach (['report_images', 'report_attachments'] as $table) {
            foreach (DB::table($table)->whereRaw('LOWER(uuid) = ?', [$mediaId])->pluck('report_id') as $reportId) {
                $legacyReportIds[(int) $reportId] = true;
            }
        }

        $revisions = Report::query()->where('report_series_id', $requestedReport->report_series_id)
            ->where('id', '<>', $requestedReport->id)->orderBy('revision_number')->orderBy('id')
            ->get(['id', 'uuid', 'payload']);

        foreach ($revisions as $revision) {
            $isCandidate = $revision->payload === null
                ? isset($legacyReportIds[$revision->id])
                : $this->payloadHasActiveMedia($revision->payload, $mediaId);

            if (! $isCandidate || ! $this->access->canRead($userId, $revision->uuid)) {
                continue;
            }

            $report = $this->findReport($revision->uuid);
            $file = $report === null ? null : $this->findReference($report, $mediaId);

            if ($file !== null) {
                return $file;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $payload */
    private function payloadHasActiveMedia(array $payload, string $mediaId): bool
    {
        $media = [
            data_get($payload, 'location.locationMap'),
            data_get($payload, 'location.municipalityInStateMap'),
            data_get($payload, 'preImplementation.image'),
            data_get($payload, 'attachments.municipalityLocationMap'),
            data_get($payload, 'attachments.topographicPlan'),
            ...(array) data_get($payload, 'photographicDocumentation.images', []),
            ...(array) data_get($payload, 'attachments.others', []),
        ];

        foreach ($media as $item) {
            if (is_array($item) && is_string($item['id'] ?? null) && strtolower($item['id']) === $mediaId) {
                return true;
            }
        }

        return false;
    }
}
