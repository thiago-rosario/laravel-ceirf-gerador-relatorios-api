<?php

declare(strict_types=1);

use src\Modules\Report\Domain\Entity\ReportImageEntity;
use src\Modules\Report\Domain\Exception\InvalidReportFileReferenceException;
use src\Modules\Report\Domain\Exception\InvalidReportImageCaptionException;
use src\Modules\Report\Domain\Exception\InvalidReportImageFormatException;
use src\Modules\Report\Domain\Exception\InvalidReportImageOrderException;
use src\Modules\Report\Domain\Exception\ReportImageTooLargeException;
use src\Modules\Report\Domain\ValueObject\ReportFileReferenceValueObject;

test('accepts the approved visual formats at the twenty-five megabyte boundary', function (string $mimeType): void {
    $file = new ReportFileReferenceValueObject('object:figure', 'figure', $mimeType, 26_214_400, str_repeat('a', 64));

    $image = new ReportImageEntity($file, 1, id: '00000000-0000-4000-8000-000000000001');

    expect($image->file())->toBe($file);
})->with([
    'PNG' => ['image/png'],
    'JPEG and JPG' => ['image/jpeg'],
    'PDF' => ['application/pdf'],
]);

test('rejects a visual file larger than twenty-five megabytes with code 2012', function (): void {
    $file = new ReportFileReferenceValueObject('object:figure', 'figure.png', 'image/png', 26_214_401, str_repeat('a', 64));

    expect(fn () => new ReportImageEntity($file, 1))->toThrow(function (ReportImageTooLargeException $exception): void {
        expect($exception->getCode())->toBe(2012);
        expect($exception->getMessage())->toBe('Cada imagem do relatório deve possuir no máximo 25 MB.');
    });
});

test('rejects visual formats outside the approved list with code 2013', function (string $mimeType): void {
    $file = new ReportFileReferenceValueObject('object:figure', 'figure', $mimeType, 100, str_repeat('a', 64));

    expect(fn () => new ReportImageEntity($file, 1))->toThrow(function (InvalidReportImageFormatException $exception): void {
        expect($exception->getCode())->toBe(2013);
        expect($exception->getMessage())->toBe('As imagens do relatório devem estar nos formatos PNG, JPEG, JPG ou PDF.');
    });
})->with([
    'GIF' => ['image/gif'],
    'SVG' => ['image/svg+xml'],
    'plain text' => ['text/plain'],
]);

test('rejects a nonpositive upload order with code 2016', function (int $order): void {
    $file = new ReportFileReferenceValueObject('object:figure', 'figure.png', 'image/png', 100, str_repeat('a', 64));

    expect(fn () => new ReportImageEntity($file, $order))->toThrow(function (InvalidReportImageOrderException $exception): void {
        expect($exception->getCode())->toBe(2016);
    });
})->with([0, -1]);

test('changes a caption while preserving the original image and upload identity', function (): void {
    $file = new ReportFileReferenceValueObject('object:figure', 'figure.png', 'image/png', 100, str_repeat('a', 64));
    $image = new ReportImageEntity($file, 3, 'Original', '00000000-0000-4000-8000-000000000001');

    $updatedImage = $image->withCaption(str_repeat('á', 500));

    expect($image->caption())->toBe('Original');
    expect($updatedImage->caption())->toBe(str_repeat('á', 500));
    expect($updatedImage->id()->value())->toBe($image->id()->value());
    expect($updatedImage->order())->toBe(3);
    expect($updatedImage->file())->toBe($file);
});

test('rejects a caption exceeding five hundred characters with code 2021', function (): void {
    $file = new ReportFileReferenceValueObject('object:figure', 'figure.png', 'image/png', 100, str_repeat('a', 64));

    expect(fn () => new ReportImageEntity($file, 1, str_repeat('á', 501)))->toThrow(function (InvalidReportImageCaptionException $exception): void {
        expect($exception->getCode())->toBe(2021);
        expect($exception->getMessage())->toBe('A legenda da imagem deve possuir no máximo 500 caracteres.');
    });
});

test('normalizes content hashes independently from the storage provider', function (): void {
    $file = new ReportFileReferenceValueObject('institutional:document:123', 'figure.png', 'image/png', 100, '  '.str_repeat('A', 64).'  ');

    expect($file->checksum())->toBe(str_repeat('a', 64));
    expect($file->storageIdentifier())->toBe('institutional:document:123');
});

test('rejects incomplete or malformed file metadata with code 2011', function (string $identifier, string $fileName, string $mimeType, int $size, string $checksum): void {
    expect(fn () => new ReportFileReferenceValueObject($identifier, $fileName, $mimeType, $size, $checksum))
        ->toThrow(function (InvalidReportFileReferenceException $exception): void {
            expect($exception->getCode())->toBe(2011);
        });
})->with([
    'missing identifier' => ['', 'figure.png', 'image/png', 100, str_repeat('a', 64)],
    'blank identifier' => ["\u{00A0}", 'figure.png', 'image/png', 100, str_repeat('a', 64)],
    'missing name' => ['object:figure', '', 'image/png', 100, str_repeat('a', 64)],
    'missing MIME type' => ['object:figure', 'figure.png', '', 100, str_repeat('a', 64)],
    'empty file' => ['object:figure', 'figure.png', 'image/png', 0, str_repeat('a', 64)],
    'negative size' => ['object:figure', 'figure.png', 'image/png', -1, str_repeat('a', 64)],
    'short hash' => ['object:figure', 'figure.png', 'image/png', 100, str_repeat('a', 63)],
    'nonhexadecimal hash' => ['object:figure', 'figure.png', 'image/png', 100, str_repeat('z', 64)],
]);
