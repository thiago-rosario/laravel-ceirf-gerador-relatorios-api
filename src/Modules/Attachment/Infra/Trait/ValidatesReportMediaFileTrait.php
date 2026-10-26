<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Infra\Trait;

use src\Modules\Attachment\Application\Exception\ReportMediaUnavailableException;
use src\Modules\Report\Domain\ValueObject\ReportFileReferenceValueObject;

trait ValidatesReportMediaFileTrait
{
    /**
     * Garante que o arquivo pertence ao diretório de mídias do relatório raiz e deriva o MIME type da extensão.
     */
    private function mediaMimeType(ReportFileReferenceValueObject $file, string $rootReportId): string
    {
        $path = $file->storageIdentifier();
        $prefix = 'reports/'.$rootReportId.'/media/';

        if (! str_starts_with($path, $prefix)
            || preg_match('/\A[a-zA-Z0-9_-]+\.(png|jpe?g|pdf)\z/', substr($path, strlen($prefix)), $matches) !== 1) {
            throw new ReportMediaUnavailableException('O arquivo do relatório não foi encontrado.', 404);
        }

        $mimeType = match ($matches[1]) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'application/pdf',
        };

        if ($file->mimeType() !== $mimeType) {
            throw new ReportMediaUnavailableException('Os metadados do arquivo do relatório são inválidos.', 422);
        }

        return $mimeType;
    }

    private function downloadFileName(ReportFileReferenceValueObject $file): string
    {
        $fileName = basename(str_replace('\\', '/', $file->fileName()));

        if (trim($fileName) === '' || in_array($fileName, ['.', '..'], true)
            || preg_match('/[\x00-\x1F\x7F]/', $fileName) === 1) {
            throw new ReportMediaUnavailableException('Os metadados do arquivo do relatório são inválidos.', 422);
        }

        return $fileName;
    }
}
