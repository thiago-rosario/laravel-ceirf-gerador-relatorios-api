<?php

declare(strict_types=1);

use src\Modules\Report\Domain\Exception\InvalidInstitutionalRepresentativeException;
use src\Modules\Report\Domain\ValueObject\ReportInstitutionalRepresentativeValueObject;

test('preserves the institutional representative name and position', function (): void {
    $representative = new ReportInstitutionalRepresentativeValueObject('Ana Souza', 'Secretária de Infraestrutura');

    expect($representative->name())->toBe('Ana Souza');
    expect($representative->position())->toBe('Secretária de Infraestrutura');
});

test('accepts names and positions with exactly 255 multibyte characters', function (): void {
    $name = str_repeat('á', 255);
    $position = str_repeat('ç', 255);

    $representative = new ReportInstitutionalRepresentativeValueObject($name, $position);

    expect($representative->name())->toBe($name);
    expect($representative->position())->toBe($position);
});

test('rejects blank or oversized representative names and positions', function (string $name, string $position): void {
    expect(fn () => new ReportInstitutionalRepresentativeValueObject($name, $position))
        ->toThrow(InvalidInstitutionalRepresentativeException::class);
})->with([
    'empty name' => ['', 'Secretária'],
    'spaces in name' => ['   ', 'Secretária'],
    'Unicode whitespace in name' => ["\u{2003}", 'Secretária'],
    'name over the character limit' => [str_repeat('á', 256), 'Secretária'],
    'empty position' => ['Ana Souza', ''],
    'spaces in position' => ['Ana Souza', '   '],
    'Unicode whitespace in position' => ['Ana Souza', "\u{2003}"],
    'position over the character limit' => ['Ana Souza', str_repeat('ç', 256)],
]);
