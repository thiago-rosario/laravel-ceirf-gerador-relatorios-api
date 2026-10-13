<?php

declare(strict_types=1);

namespace src\Modules\Report\Infra\Service;

use Illuminate\Support\Facades\Storage;
use src\Modules\Report\Application\DTO\GeneratedReportDataDTO;
use src\Modules\Report\Application\DTO\ReportPdfFileDTO;
use src\Modules\Report\Application\Exception\ReportPdfUnavailableException;
use src\Modules\Report\Application\Interfaces\Service\ReportPdfStorageInterface;
use Throwable;

class ReportPdfStorage implements ReportPdfStorageInterface
{
    public function open(string $reportId, GeneratedReportDataDTO $document): ReportPdfFileDTO
    {
        $prefix = 'reports/'.$reportId.'/documents/';
        $path = $document->storageIdentifier;

        if (! str_starts_with($path, $prefix)
            || ! preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\.pdf$/i', substr($path, strlen($prefix)))) {
            throw new ReportPdfUnavailableException('O arquivo PDF do relatório não foi encontrado.', 404);
        }

        $stream = null;

        try {
            $disk = Storage::disk('local');

            if (! $disk->exists($path)) {
                throw new ReportPdfUnavailableException('O arquivo PDF do relatório não foi encontrado.', 404);
            }

            $stream = $disk->readStream($path);

            if (! is_resource($stream)) {
                throw new ReportPdfUnavailableException('Não foi possível ler o PDF no armazenamento.', 500);
            }

            $metadata = fstat($stream);
            $size = $metadata === false ? 0 : $metadata['size'];
            $header = fread($stream, 1024);

            if ($size < 12 || $header === false || ! str_starts_with($header, '%PDF-')
                || fseek($stream, max(0, $size - 1024)) !== 0) {
                throw new ReportPdfUnavailableException('O PDF do relatório está inválido ou incompleto.', 422);
            }

            $ending = fread($stream, 1024);

            if ($ending === false || ! str_contains($ending, '%%EOF') || ! rewind($stream)) {
                throw new ReportPdfUnavailableException('O PDF do relatório está inválido ou incompleto.', 422);
            }

            return new ReportPdfFileDTO($stream, $document->fileName, $size);
        } catch (Throwable $exception) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            throw $exception;
        }
    }
}
