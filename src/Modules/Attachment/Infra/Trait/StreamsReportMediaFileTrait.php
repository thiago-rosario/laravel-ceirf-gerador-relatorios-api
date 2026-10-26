<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Infra\Trait;

use finfo;
use Illuminate\Support\Facades\Storage;
use src\Modules\Attachment\Application\DTO\GetMediaReportOutputDTO;
use src\Modules\Attachment\Application\Exception\ReportMediaUnavailableException;
use src\Modules\Report\Domain\ValueObject\ReportFileReferenceValueObject;
use Throwable;

trait StreamsReportMediaFileTrait
{
    private function open(ReportFileReferenceValueObject $file, string $fileName, string $mimeType): GetMediaReportOutputDTO
    {
        $stream = null;

        try {
            $stream = $this->readStream($file->storageIdentifier());
            $size = $this->verifyStream($stream, $file, $mimeType);

            return new GetMediaReportOutputDTO($stream, $fileName, $mimeType, $size);
        } catch (Throwable $exception) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            throw $exception;
        }
    }

    /** @return resource */
    private function readStream(string $path)
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            throw new ReportMediaUnavailableException('O arquivo do relatório não foi encontrado.', 404);
        }

        $stream = $disk->readStream($path);

        if (! is_resource($stream)) {
            throw new ReportMediaUnavailableException('Não foi possível ler o arquivo do relatório.', 500);
        }

        return $stream;
    }

    /**
     * Confere tamanho, checksum SHA-256 e assinatura do conteúdo e devolve o stream posicionado no início.
     *
     * @param  resource  $stream
     */
    private function verifyStream($stream, ReportFileReferenceValueObject $file, string $mimeType): int
    {
        $metadata = fstat($stream);
        $header = fread($stream, 8192);

        if ($metadata === false || $header === false || ! rewind($stream)) {
            throw new ReportMediaUnavailableException('Não foi possível ler o arquivo do relatório.', 500);
        }

        $checksum = hash_init('sha256');
        $size = hash_update_stream($checksum, $stream);

        if ($metadata['size'] !== $file->sizeBytes() || $size !== $file->sizeBytes()
            || ! hash_equals($file->checksum(), hash_final($checksum))
            || (new finfo(FILEINFO_MIME_TYPE))->buffer($header) !== $mimeType) {
            throw new ReportMediaUnavailableException('O conteúdo do arquivo do relatório é inválido.', 422);
        }

        if (! rewind($stream)) {
            throw new ReportMediaUnavailableException('Não foi possível ler o arquivo do relatório.', 500);
        }

        return $size;
    }
}
