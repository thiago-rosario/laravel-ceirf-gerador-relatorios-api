<?php

declare(strict_types=1);

use src\Modules\Report\Application\Service\ReportDataMapperService;
use src\Modules\Report\Domain\Entity\ReportAttachmentEntity;
use src\Modules\Report\Domain\Enum\ForceEnum;
use src\Modules\Report\Domain\Enum\ReportAttachmentTypeEnum;
use src\Modules\Report\Domain\Enum\ReportSizeEnum;
use src\Modules\Report\Domain\ValueObject\MunicipalityValueObject;
use src\Modules\Report\Domain\ValueObject\ReportAttachmentsValueObject;
use src\Modules\Report\Domain\ValueObject\ReportConclusionValueObject;
use src\Modules\Report\Domain\ValueObject\ReportCoverValueObject;
use src\Modules\Report\Domain\ValueObject\ReportGeneralInformationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportInstitutionalRepresentativeValueObject;
use src\Modules\Report\Domain\ValueObject\ReportLocationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPhotographicDocumentationValueObject;
use src\Modules\Report\Domain\ValueObject\ReportPreImplementationValueObject;
use src\Modules\Report\Domain\ValueObject\SeiNumberValueObject;
use src\Modules\Report\Presentation\Resources\ReportPdfResource;
use Tests\Fixtures\ReportApplicationFixtures;

test('numbers figures in document order while preserving their source upload order', function (): void {
    $location = ReportApplicationFixtures::image(8);
    $state = ReportApplicationFixtures::image(4);
    $preImplementation = ReportApplicationFixtures::image(9);
    $firstPhotograph = ReportApplicationFixtures::image(12);
    $secondPhotograph = ReportApplicationFixtures::image(15);
    $report = ReportApplicationFixtures::completeReport([
        'location' => new ReportLocationValueObject($location, $state),
        'preImplementation' => new ReportPreImplementationValueObject($preImplementation),
        'photographicDocumentation' => new ReportPhotographicDocumentationValueObject([$firstPhotograph, $secondPhotograph]),
    ]);
    $sourceImages = serialize($report->uploadedImages());
    $resource = new ReportPdfResource(app(ReportDataMapperService::class)->map($report), [
        $firstPhotograph->file()->storageIdentifier() => ['data:image/png;base64,first-photograph'],
    ]);

    $data = $resource->toArray();

    expect(array_column($data['figures'], 'key'))->toBe([
        'location-map',
        'state-map',
        'pre-implementation-image',
        'photograph-'.$firstPhotograph->id()->value(),
        'photograph-'.$secondPhotograph->id()->value(),
    ]);
    expect(array_column($data['figures'], 'number'))->toBe([1, 2, 3, 4, 5]);
    expect(array_column($data['figures'], 'caption'))->toBe([
        'Mapa de Localização',
        'Localização do município em relação ao estado da Bahia',
        'Sugestão de pré-implantação COTEC',
        'Figura do terreno 12',
        'Figura do terreno 15',
    ]);
    expect($data['figures'][3]['sources'])->toBe(['data:image/png;base64,first-photograph']);
    expect(serialize($report->uploadedImages()))->toBe($sourceImages);
});

test('omits missing optional figures and continues numbering photographs and attachments', function (): void {
    $state = ReportApplicationFixtures::image(4);
    $photograph = ReportApplicationFixtures::image(12);
    $attachmentImage = ReportApplicationFixtures::image(20);
    $attachment = new ReportAttachmentEntity(
        type: ReportAttachmentTypeEnum::OTHER,
        file: $attachmentImage->file(),
        description: 'Documento adicional do terreno',
        id: $attachmentImage->id(),
    );
    $report = ReportApplicationFixtures::completeReport([
        'location' => new ReportLocationValueObject(municipalityInStateMap: $state),
        'photographicDocumentation' => new ReportPhotographicDocumentationValueObject([$photograph]),
        'attachments' => new ReportAttachmentsValueObject(others: [$attachment]),
    ]);
    $resource = new ReportPdfResource(app(ReportDataMapperService::class)->map($report));

    $data = $resource->toArray();

    expect(array_column($data['figures'], 'key'))->toBe([
        'state-map',
        'photograph-'.$photograph->id()->value(),
        'attachment-'.$attachment->id()->value(),
    ]);
    expect(array_column($data['figures'], 'number'))->toBe([1, 2, 3]);
    expect(array_column($data['sections'], 'anchor'))->toContain('attachment-'.$attachment->id()->value())
        ->not->toContain('municipality-map', 'topographic-plan');
});

test('presents the report chapters and readable infrastructure and checklist labels', function (): void {
    $report = ReportApplicationFixtures::completeReport();
    $resource = new ReportPdfResource(app(ReportDataMapperService::class)->map($report));

    $data = $resource->toArray();

    expect(array_column($data['sections'], 'title'))->toBe([
        '1. Informações Gerais',
        '2. Objetivo',
        '3. Localização do Terreno',
        '4. Condições do Terreno',
        '5. Infraestrutura Existente',
        '6. Sugestão de Pré-implantação',
        '7. Documentação Fotográfica',
        '8. Conclusão',
        '9. Anexos',
        '9.1. Checklist do Terreno',
    ]);
    expect($data['infrastructure']['Rede de água'])->toBe('SIM');
    expect($data['infrastructure']['Telefonia'])->toBe('NÃO SE APLICA');
    expect($data['checklist']['Faixa de domínio ou área não edificável'])->toBe('NÃO');
});

test('uses the supplied document pages for the summary and figure list links', function (): void {
    $report = ReportApplicationFixtures::completeReport();
    $resource = new ReportPdfResource(app(ReportDataMapperService::class)->map($report));

    $html = $resource->render(['conclusion' => 21, 'figure-1' => 7]);

    $document = new DOMDocument;
    $document->loadHTML($html);
    $xpath = new DOMXPath($document);
    $chapterPage = $xpath->query('//table[contains(@class, "index")]//tr[td/a[@href="#conclusion"]]/td[@class="page"]')->item(0);
    $figurePage = $xpath->query('//table[contains(@class, "index")]//tr[td/a[@href="#figure-1"]]/td[@class="page"]')->item(0);
    expect($chapterPage?->textContent)->toBe('21');
    expect($figurePage?->textContent)->toBe('7');
});

test('escapes every editable text field and preserves the conclusion line breaks', function (): void {
    $photograph = ReportApplicationFixtures::image(12)->withCaption('<script>caption()</script>');
    $attachmentImage = ReportApplicationFixtures::image(20);
    $attachment = new ReportAttachmentEntity(
        type: ReportAttachmentTypeEnum::OTHER,
        file: $attachmentImage->file(),
        description: '<script>attachment()</script>',
        id: $attachmentImage->id(),
    );
    $report = ReportApplicationFixtures::completeReport([
        'cover' => new ReportCoverValueObject(
            municipality: new MunicipalityValueObject(1, '<script>municipality()</script>'),
            force: ForceEnum::PM,
            size: ReportSizeEnum::ONE_B,
            typology: '<script>typology()</script>',
            seiNumber: new SeiNumberValueObject('<script>sei()</script>'),
        ),
        'generalInformation' => new ReportGeneralInformationValueObject(
            inspectionDate: new DateTimeImmutable('2020-01-01'),
            collaborators: '<script>collaborators()</script>',
            institutionalRepresentatives: [
                new ReportInstitutionalRepresentativeValueObject('<script>representative()</script>', '<script>position()</script>'),
            ],
        ),
        'photographicDocumentation' => new ReportPhotographicDocumentationValueObject([$photograph]),
        'attachments' => new ReportAttachmentsValueObject(others: [$attachment]),
        'conclusion' => new ReportConclusionValueObject("<script>conclusion()</script>\nSegunda linha da conclusão."),
    ]);
    $resource = new ReportPdfResource(app(ReportDataMapperService::class)->map($report));

    $html = $resource->render();

    expect($html)->not->toContain('<script>')
        ->toContain('&lt;script&gt;municipality()&lt;/script&gt;')
        ->toContain('&lt;script&gt;typology()&lt;/script&gt;')
        ->toContain('&lt;script&gt;sei()&lt;/script&gt;')
        ->toContain('&lt;script&gt;collaborators()&lt;/script&gt;')
        ->toContain('&lt;script&gt;representative()&lt;/script&gt;')
        ->toContain('&lt;script&gt;position()&lt;/script&gt;')
        ->toContain('&lt;script&gt;caption()&lt;/script&gt;')
        ->toContain('&lt;script&gt;attachment()&lt;/script&gt;')
        ->toContain('&lt;script&gt;conclusion()&lt;/script&gt;');
    expect(preg_replace('/<br\s*\/?\s*>/i', '', $html))->toContain("&lt;script&gt;conclusion()&lt;/script&gt;\nSegunda linha da conclusão.");
});

test('renders institutional representatives and positions independently from present collaborators', function (): void {
    $report = ReportApplicationFixtures::completeReport([
        'generalInformation' => new ReportGeneralInformationValueObject(
            inspectionDate: new DateTimeImmutable('2020-01-01'),
            collaborators: 'Ana Silva e João Santos',
            institutionalRepresentatives: [
                new ReportInstitutionalRepresentativeValueObject('Maria Oliveira', 'Secretária municipal'),
                new ReportInstitutionalRepresentativeValueObject('José Souza', 'Diretor de infraestrutura'),
            ],
        ),
    ]);
    $resource = new ReportPdfResource(app(ReportDataMapperService::class)->map($report));

    $html = $resource->render();

    expect($html)->toContain('<strong>Colaboradores presentes:</strong> Ana Silva e João Santos')
        ->toContain('<strong>Representantes institucionais:</strong>')
        ->toContain('Maria Oliveira — Secretária municipal')
        ->toContain('José Souza — Diretor de infraestrutura');
});

test('omits the institutional representatives block when the report has none', function (): void {
    $report = ReportApplicationFixtures::completeReport();
    $resource = new ReportPdfResource(app(ReportDataMapperService::class)->map($report));

    $html = $resource->render();

    expect($html)->not->toContain('Representantes institucionais:')
        ->toContain('<strong>Colaboradores presentes:</strong> Ana Silva e João Santos');
});
