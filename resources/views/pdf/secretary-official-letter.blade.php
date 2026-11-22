<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $document->title }}</title>
    <style>
        @page {
            margin: {{ $layout['top'] + ($layout['repeat_header'] ? 26 : 0) }}mm {{ $layout['right'] }}mm {{ $layout['bottom'] + (($contacts || $layout['footer'] || $partials['footer']) ? 13 : 0) }}mm {{ $layout['left'] }}mm;
        }
        body {
            font: {{ $layout['font_size'] }}pt/{{ $layout['line_height'] }} {{ $layout['font'] === 'serif' ? '"DejaVu Serif", serif' : 'Calibri, Carlito, "DejaVu Sans", sans-serif' }};
            color: #151515;
        }
        p { margin: 0 0 {{ $layout['spacing'] }}pt; }
        .institution { text-align: {{ $layout['header_alignment'] }}; margin-bottom: {{ $layout['header_spacing'] }}mm; font-size: 10pt; line-height: 1.25; page-break-inside: avoid; }
        .institution.repeat { position: fixed; top: -26mm; left: 0; right: 0; max-height: 23mm; overflow: hidden; }
        .institution.border { border-bottom: 1px solid #737a75; padding-bottom: 2mm; }
        .letterhead { display: inline-table; width: auto; }
        .letterhead td { padding: 0; vertical-align: middle; }
        .letterhead img { max-width: {{ $layout['logo_width'] }}mm; max-height: 17mm; margin-right: 4mm; }
        .letterhead small { display: block; font-size: 8.5pt; color: #526057; margin-top: 1mm; }
        .date { text-align: right; margin-top: 4mm; }
        .recipient { margin: 5mm 0; white-space: pre-line; }
        .subject { margin-bottom: 5mm; }
        .salutation, .closing, .body > p { text-indent: {{ $layout['indent'] }}mm; }
        .body { text-align: left; overflow-wrap: break-word; }
        .body table { width: 100%; border-collapse: collapse; margin: 2mm 0; }
        .body td, .body th { border: 1px solid #737a75; padding: 1.5mm; text-indent: 0; }
        .body img { max-width: 100%; max-height: 190mm; }
        .body figure { margin: 2mm 0; page-break-inside: avoid; }
        figcaption { font-size: 9pt; }
        blockquote { border-left: 1px solid #737a75; margin-left: 5mm; padding-left: 3mm; }
        .signature { margin-top: 14mm; text-align: center; page-break-inside: avoid; }
        .signature strong { display: inline-block; min-width: 52mm; padding-top: 2mm; border-top: 1px solid #737a75; }
        footer { position: fixed; bottom: -20mm; height: 17mm; overflow: hidden; left: 0; right: 0; text-align: center; font-size: 8pt; }
        footer p { margin: 0 0 2mm; }
    </style>
</head>
<body>
    <header @class(['institution', 'repeat' => $layout['repeat_header'], 'border' => $layout['separator']])>
        @if ($partials['header'])
            {!! $partials['header'] !!}
        @else
            <table class="letterhead"><tr>
                @if ($logo)<td><img src="{{ $logo }}" alt=""></td>@endif
                <td>
                    <strong>{{ $name }}{{ $layout['acronym'] && !\Illuminate\Support\Str::contains($name, $layout['acronym'], ignoreCase: true) ? ' · '.$layout['acronym'] : '' }}</strong>
                    @if ($layout['department'])<br>{{ $layout['department'] }}@endif
                    @if ($branding['registration'])<small>{{ $branding['registration'] }}</small>@endif
                </td>
            </tr></table>
        @endif
    </header>
    @if ($contacts || $layout['footer'] || $partials['footer'])
        <footer>
            @if ($partials['footer']){!! $partials['footer'] !!}@endif
            @if ($layout['footer'])<p>{{ $layout['footer'] }}</p>@endif
            @if ($contacts)<p>{{ implode(' · ', $contacts) }}</p>@endif
        </footer>
    @endif
    @if ($number)<strong>OFÍCIO Nº {{ $number }}</strong>@endif
    @if (!empty($letter['city']) || $date)
        <p class="date">{{ implode(', ', array_filter([$letter['city'] ?? '', $date])) }}{{ $date ? '.' : '' }}</p>
    @endif
    <div class="recipient">
        @foreach (['recipient', 'recipient_position', 'institution', 'recipient_address'] as $field)
            @if (!empty($letter[$field]))<p>{{ $letter[$field] }}</p>@endif
        @endforeach
    </div>
    @if (!empty($letter['subject']))<p class="subject"><strong>Assunto: {{ $letter['subject'] }}</strong></p>@endif
    @if (!empty($letter['salutation']))<p class="salutation">{{ $letter['salutation'] }}</p>@endif
    <main class="body">{!! $content !!}</main>
    @if (!empty($letter['closing']))<p class="closing">{{ $letter['closing'] }}</p>@endif
    @if (!empty($letter['signatory']))<div class="signature"><strong>{{ $letter['signatory'] }}</strong><br>{{ $letter['signatory_position'] ?? '' }}</div>@endif
</body>
</html>
