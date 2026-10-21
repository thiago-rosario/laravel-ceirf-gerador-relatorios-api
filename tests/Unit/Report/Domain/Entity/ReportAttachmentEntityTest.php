<?php

declare(strict_types=1);

use src\Modules\Report\Domain\Entity\ReportAttachmentEntity;
use src\Modules\Report\Domain\Enum\ReportAttachmentTypeEnum;
use src\Modules\Report\Domain\Exception\InvalidReportAttachmentException;
use src\Modules\Report\Domain\Exception\InvalidReportImageFormatException;
use src\Modules\Report\Domain\Exception\ReportAttachmentDescriptionRequiredException;
use src\Modules\Report\Domain\Exception\ReportImageTooLargeException;
use src\Modules\Report\Domain\Validation\ReportAttachmentValidation;
use src\Modules\Report\Domain\ValueObject\ReportAttachmentsValueObject;
use src\Modules\Report\Domain\ValueObject\ReportFileReferenceValueObject;

test('allows an other attachment without its description in a draft but blocks generation with code 2017', function (string $description): void {
    $file = new ReportFileReferenceValueObject('object:annex', 'annex.pdf', 'application/pdf', 100, str_repeat('a', 64));
    $attachment = new ReportAttachmentEntity(ReportAttachmentTypeEnum::OTHER, $file, $description, '00000000-0000-4000-8000-000000000001');

    expect(fn () => ReportAttachmentValidation::validateForGeneration($attachment))
        ->toThrow(function (ReportAttachmentDescriptionRequiredException $exception): void {
            expect($exception->getCode())->toBe(2017);
        });
})->with(['', '   ', "\u{00A0}"]);

test('completes an other attachment description without changing its original identity or file', function (): void {
    $file = new ReportFileReferenceValueObject('object:annex', 'annex.pdf', 'application/pdf', 100, str_repeat('a', 64));
    $attachment = new ReportAttachmentEntity(ReportAttachmentTypeEnum::OTHER, $file, id: '00000000-0000-4000-8000-000000000001');

    $updatedAttachment = $attachment->withDescription(str_repeat('á', 500));
    ReportAttachmentValidation::validateForGeneration($updatedAttachment);

    expect($attachment->description())->toBe('');
    expect($updatedAttachment->description())->toBe(str_repeat('á', 500));
    expect($updatedAttachment->id()->value())->toBe($attachment->id()->value());
    expect($updatedAttachment->file())->toBe($file);
});

test('rejects an attachment description exceeding five hundred characters with code 2023', function (): void {
    $file = new ReportFileReferenceValueObject('object:annex', 'annex.pdf', 'application/pdf', 100, str_repeat('a', 64));

    expect(fn () => new ReportAttachmentEntity(ReportAttachmentTypeEnum::OTHER, $file, str_repeat('á', 501)))
        ->toThrow(function (InvalidReportAttachmentException $exception): void {
            expect($exception->getCode())->toBe(2023);
            expect($exception->getMessage())->toBe('A descrição do anexo deve possuir no máximo 500 caracteres.');
        });
});

test('prevents attachment uploads from bypassing the visual file size limit', function (): void {
    $file = new ReportFileReferenceValueObject('object:annex', 'annex.pdf', 'application/pdf', 26_214_401, str_repeat('a', 64));

    expect(fn () => new ReportAttachmentEntity(ReportAttachmentTypeEnum::OTHER, $file))
        ->toThrow(function (ReportImageTooLargeException $exception): void {
            expect($exception->getCode())->toBe(2012);
        });
});

test('prevents attachment uploads from bypassing approved visual file formats', function (): void {
    $file = new ReportFileReferenceValueObject('object:annex', 'annex.txt', 'text/plain', 100, str_repeat('a', 64));

    expect(fn () => new ReportAttachmentEntity(ReportAttachmentTypeEnum::OTHER, $file))
        ->toThrow(function (InvalidReportImageFormatException $exception): void {
            expect($exception->getCode())->toBe(2013);
        });
});

test('rejects a named attachment in the wrong section with code 2023', function (): void {
    $file = new ReportFileReferenceValueObject('object:annex', 'annex.pdf', 'application/pdf', 100, str_repeat('a', 64));
    $attachment = new ReportAttachmentEntity(ReportAttachmentTypeEnum::OTHER, $file, 'Anexo');

    expect(fn () => new ReportAttachmentsValueObject(municipalityLocationMap: $attachment))
        ->toThrow(function (InvalidReportAttachmentException $exception): void {
            expect($exception->getCode())->toBe(2023);
        });
});
