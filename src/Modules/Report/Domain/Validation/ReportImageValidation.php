<?php

declare(strict_types=1);

namespace src\Modules\Report\Domain\Validation;

use src\Modules\Report\Domain\Entity\ReportImageEntity;
use src\Modules\Report\Domain\Exception\InvalidReportImageCaptionException;
use src\Modules\Report\Domain\Exception\InvalidReportImageFormatException;
use src\Modules\Report\Domain\Exception\InvalidReportImageOrderException;
use src\Modules\Report\Domain\Exception\ReportImageTooLargeException;
use src\Modules\Report\Domain\ValueObject\ReportFileReferenceValueObject;

final class ReportImageValidation
{
    public const int MAXIMUM_SIZE_BYTES = 25 * 1024 * 1024;

    public const array ALLOWED_MIME_TYPES = ['image/png', 'image/jpeg', 'application/pdf'];

    public static function validate(ReportImageEntity $image): void
    {
        self::validateFile($image->file());

        if ($image->order() < 1) {
            throw new InvalidReportImageOrderException;
        }

        if (mb_strlen($image->caption()) > 500) {
            throw new InvalidReportImageCaptionException;
        }
    }

    public static function validateFile(ReportFileReferenceValueObject $file): void
    {
        if ($file->sizeBytes() > self::MAXIMUM_SIZE_BYTES) {
            throw new ReportImageTooLargeException;
        }

        if (! in_array($file->mimeType(), self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidReportImageFormatException;
        }
    }
}
