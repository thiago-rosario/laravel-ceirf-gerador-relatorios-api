<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Infra\Trait;

use src\Modules\Attachment\Application\Exception\ReportMediaUnavailableException;
use src\Modules\Report\Domain\Entity\ReportEntity;
use src\Modules\Report\Domain\Exception\InvalidReportAttachmentException;
use src\Modules\Report\Domain\Exception\InvalidReportFileReferenceException;
use src\Modules\Report\Domain\Exception\InvalidReportImageFormatException;
use src\Modules\Report\Domain\Exception\ReportImageTooLargeException;
use src\Modules\Report\Domain\Validation\ReportMediaValidation;
use src\Modules\Report\Domain\ValueObject\ReportFileReferenceValueObject;

trait ResolvesReportMediaReferenceTrait
{
    private function findReport(string $reportId): ?ReportEntity
    {
        try {
            return $this->reports->findById($reportId);
        } catch (InvalidReportFileReferenceException|InvalidReportImageFormatException|ReportImageTooLargeException|InvalidReportAttachmentException $exception) {
            throw new ReportMediaUnavailableException('Os metadados do arquivo do relatório são inválidos.', 422, $exception);
        }
    }

    private function findReference(ReportEntity $report, string $mediaId, bool $includeHistory = false): ?ReportFileReferenceValueObject
    {
        $media = [...ReportMediaValidation::images($report), ...($report->attachments()?->files() ?? [])];

        if ($includeHistory) {
            $media = [...$media, ...$report->uploadedImages()];
        }

        foreach ($media as $item) {
            if (strtolower($item->id()->value()) === $mediaId) {
                return $item->file();
            }
        }

        return null;
    }
}
