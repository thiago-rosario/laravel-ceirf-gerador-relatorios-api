<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Vistoria de Terreno - {{ $report->cover?->municipality?->name }}</title>
    <style>
        @if(config('report_pdf.arial_regular'))
        @font-face { font-family: Arial; font-style: normal; font-weight: normal; src: url("{{ config('report_pdf.arial_regular') }}") format("truetype"); }
        @endif
        @if(config('report_pdf.arial_bold'))
        @font-face { font-family: Arial; font-style: normal; font-weight: bold; src: url("{{ config('report_pdf.arial_bold') }}") format("truetype"); }
        @endif
        @page { margin: 3cm 2cm 2cm 3cm; }
        body { color: #000; font-family: Arial, "DejaVu Sans", sans-serif; font-size: 12pt; line-height: 1.5; }
        h1, h2 { font-size: 14pt; font-weight: bold; line-height: 1.5; margin: 0 0 18pt; page-break-after: avoid; }
        h3 { font-size: 12pt; line-height: 1.5; margin: 18pt 0 12pt; page-break-after: avoid; }
        p { margin: 0 0 12pt; text-align: justify; text-indent: 1.25cm; overflow-wrap: break-word; }
        .chapter, .front-matter { page-break-before: always; }
        .cover { text-align: center; }
        .institution { font-size: 12pt; margin-bottom: 90pt; }
        .cover h1 { margin-bottom: 42pt; text-transform: uppercase; }
        .cover-details { width: 100%; border-collapse: collapse; text-align: center; }
        .cover-details td { padding: 6pt; }
        .cover-date { margin-top: 65pt; text-align: center; text-indent: 0; }
        .index { width: 100%; border-collapse: collapse; }
        .index td { padding: 4pt 0; vertical-align: top; }
        .index .page { width: 28pt; text-align: right; }
        .index a { color: #000; text-decoration: none; }
        .figure-index { font-size: 11pt; }
        .information { text-indent: 0; text-align: left; }
        .answers { width: 100%; border-collapse: collapse; font-size: 10pt; line-height: 1.35; }
        .answers th, .answers td { border: 0.7pt solid #000; padding: 5pt; vertical-align: middle; }
        .answers th { text-align: center; font-weight: bold; }
        .answers .answer { width: 14%; text-align: center; }
        .answers .question { width: 58%; }
        .answers tr { page-break-inside: avoid; }
        .figure { text-align: center; margin: 12pt 0 18pt; page-break-inside: avoid; }
        .figure img { max-width: 100%; max-height: 17cm; }
        .caption { font-size: 10pt; line-height: 1.35; text-align: center; text-indent: 0; margin: 6pt 0 0; }
        .observation { font-size: 10pt; line-height: 1.5; text-indent: 0; }
        .conclusion { white-space: pre-line; }
    </style>
</head>
<body>
    <div class="cover">
        <div class="institution">GOVERNO DO ESTADO DA BAHIA<br>SECRETARIA DA SEGURANÇA PÚBLICA<br>CEIRF</div>
        <h1>Relatório de Vistoria de Terreno</h1>
        <table class="cover-details">
            <tr><td><strong>{{ $report->cover?->municipality?->name }}</strong></td></tr>
            <tr><td>{{ $report->cover?->force }} - {{ $report->cover?->size }}</td></tr>
            <tr><td>{{ $report->cover?->typology }}</td></tr>
            <tr><td>Nº SEI: {{ $report->cover?->seiNumber }}</td></tr>
            @if($report->revisionLabel)
                <tr><td>{{ $report->revisionLabel }}</td></tr>
            @endif
        </table>
        <p class="cover-date">{{ $report->generalInformation?->inspectionDate?->format('Y') }}</p>
    </div>
    <div class="front-matter">
        <h2>Sumário</h2>
        <table class="index">
            @foreach($sections as $section)
                <tr>
                    <td><a href="#{{ $section['anchor'] }}">{{ $section['title'] }}</a></td>
                    <td class="page">{{ $pages[$section['anchor']] ?? '' }}</td>
                </tr>
            @endforeach
        </table>
    </div>
    <div class="front-matter">
        <h2>Lista de Figuras</h2>
        <table class="index figure-index">
            @foreach($figures as $figure)
                <tr>
                    <td><a href="#{{ $figure['anchor'] }}">Figura {{ $figure['number'] }} - {{ $figure['caption'] }}</a></td>
                    <td class="page">{{ $pages[$figure['anchor']] ?? '' }}</td>
                </tr>
            @endforeach
        </table>
    </div>
    <div class="chapter">
        <h2 id="general-information" data-pdf-anchor="general-information">1. Informações Gerais</h2>
        <p class="information"><strong>Município:</strong> {{ $report->cover?->municipality?->name }}</p>
        <p class="information"><strong>Data da vistoria:</strong> {{ $report->generalInformation?->inspectionDate?->format('d/m/Y') }}</p>
        <p class="information"><strong>Colaboradores presentes:</strong> {{ $report->generalInformation?->collaborators }}</p>
        @if($report->generalInformation?->institutionalRepresentatives)
            <p class="information"><strong>Representantes institucionais:</strong>
                @foreach($report->generalInformation->institutionalRepresentatives as $representative)
                    <br>{{ $representative->name }} — {{ $representative->position }}
                @endforeach
            </p>
        @endif
        <p class="information"><strong>Força:</strong> {{ $report->cover?->force }}</p>
        <p class="information"><strong>Tamanho:</strong> {{ $report->cover?->size }}</p>
        <p class="information"><strong>Tipologia:</strong> {{ $report->cover?->typology }}</p>
        <p class="information"><strong>Nº SEI:</strong> {{ $report->cover?->seiNumber }}</p>
    </div>
    <div class="chapter">
        <h2 id="objective" data-pdf-anchor="objective">2. Objetivo</h2>
        <p>Registrar as informações obtidas na vistoria do terreno destinado à implantação de {{ $report->cover?->typology }}, no município de {{ $report->cover?->municipality?->name }}, para subsidiar a avaliação de sua viabilidade técnica.</p>
    </div>
    <div class="chapter">
        <h2 id="location" data-pdf-anchor="location">3. Localização do Terreno</h2>
        <p>O terreno vistoriado está localizado no município de {{ $report->cover?->municipality?->name }}, no estado da Bahia.</p>
        @foreach($figures as $figure)
            @if(in_array($figure['key'], ['location-map', 'state-map'], true))
                @include('report::figure', ['figure' => $figure])
            @endif
        @endforeach
    </div>
    <div class="chapter">
        <h2 id="conditions" data-pdf-anchor="conditions">4. Condições do Terreno</h2>
        <p>A avaliação das condições do terreno considera as dimensões, a declividade, a drenagem, o histórico de alagamentos e as restrições de ocupação registradas no Checklist do Terreno, apresentado nos anexos deste relatório.</p>
    </div>
    <div class="chapter">
        <h2 id="infrastructure" data-pdf-anchor="infrastructure">5. Infraestrutura Existente</h2>
        @include('report::answers', ['answers' => $infrastructure])
    </div>
    <div class="chapter">
        <h2 id="pre-implementation" data-pdf-anchor="pre-implementation">6. Sugestão de Pré-implantação</h2>
        @if($report->preImplementation?->image)
            @foreach($figures as $figure)
                @if($figure['key'] === 'pre-implementation-image')
                    @include('report::figure', ['figure' => $figure])
                @endif
            @endforeach
            <p class="observation">Obs.: A pré-implantação foi realizada com imagens de drone e não substitui o projeto de implantação, que deverá ser realizada pela Coordenação de Projetos após a realização dos estudos planialtimétricos e sondagens.</p>
        @else
            <p>Não foi apresentada sugestão de pré-implantação.</p>
        @endif
    </div>
    <div class="chapter">
        <h2 id="photographs" data-pdf-anchor="photographs">7. Documentação Fotográfica</h2>
        @forelse($report->photographicDocumentation?->images ?? [] as $image)
            @foreach($figures as $figure)
                @if($figure['key'] === 'photograph-'.$image->id)
                    @include('report::figure', ['figure' => $figure])
                @endif
            @endforeach
        @empty
            <p>Não foram apresentadas fotografias adicionais.</p>
        @endforelse
    </div>
    <div class="chapter">
        <h2 id="conclusion" data-pdf-anchor="conclusion">8. Conclusão</h2>
        <p class="conclusion">{{ $report->conclusion?->content }}</p>
    </div>
    <div class="chapter">
        <h2 id="attachments" data-pdf-anchor="attachments">9. Anexos</h2>
        <h3 id="checklist" data-pdf-anchor="checklist">9.1. Checklist do Terreno</h3>
        @include('report::answers', ['answers' => $checklist])
        @foreach($figures as $figure)
            @if(in_array($figure['key'], ['municipality-map', 'topographic-plan'], true) || str_starts_with($figure['key'], 'attachment-'))
                <div class="chapter">
                    <h3 id="{{ $figure['key'] }}" data-pdf-anchor="{{ $figure['key'] }}">{{ $figure['caption'] }}</h3>
                    @include('report::figure', ['figure' => $figure])
                </div>
            @endif
        @endforeach
    </div>
</body>
</html>
