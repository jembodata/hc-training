<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        {!! $compiledCss !!}

        @page {
            size: A4 landscape;
            margin: 0;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html,
        body {
            width: 297mm;
            height: 210mm;
            margin: 0;
            padding: 0;
            overflow: hidden;
            background: #ffffff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .certificate-page {
            position: relative;
            width: 297mm;
            height: 210mm;
            overflow: hidden;
            background: #ffffff;
        }

        .certificate-element {
            position: absolute;
            z-index: 20;
            box-sizing: border-box;
            padding: 1mm 2.5mm;
            overflow-wrap: break-word;
        }
    </style>
</head>
<body>
@php
    $layout = $template['layout_settings'];

    $elementStyle = static function (array $item): string {
        return sprintf(
            'left:%s%%;top:%s%%;width:%s%%;font-size:%spx;',
            $item['x'],
            $item['y'],
            $item['width'],
            $item['font_size'],
        );
    };

    $elements = [
        'header' => [
            'visible' => ($template['header_text'] ?? '') !== '',
            'content' => $renderText($template['header_text'] ?? ''),
            'classes' => 'font-bold',
        ],
        'name' => [
            'visible' => true,
            'content' => $variables['employee_name'] ?? '',
            'classes' => 'font-bold text-blue-700',
        ],
        'body' => [
            'visible' => true,
            'content' => $renderText($template['body_text'] ?? ''),
            'classes' => 'whitespace-pre-line leading-relaxed',
        ],
        'signature_line' => [
            'visible' => ($template['signature_line'] ?? '') !== '',
            'content' => $renderText($template['signature_line'] ?? ''),
            'classes' => 'leading-relaxed',
        ],
        'signer_label' => [
            'visible' => (bool) ($template['digital_signature_enabled'] ?? false),
            'content' => $renderText($template['signer_label'] ?? ''),
            'classes' => 'font-semibold leading-relaxed',
        ],
        'signer_position' => [
            'visible' => (bool) ($template['digital_signature_enabled'] ?? false),
            'content' => $renderText($template['signer_position'] ?? ''),
            'classes' => 'leading-relaxed',
        ],
    ];
@endphp

<main class="certificate-page font-sans text-slate-900">
    @if ($template['design'] === \App\Models\CertificateTemplate::DESIGN_CUSTOM_UPLOAD && $backgroundDataUri)
        <img
            src="{{ $backgroundDataUri }}"
            alt=""
            class="absolute inset-0 h-full w-full object-cover"
        >
    @endif

    @if ($template['design'] === \App\Models\CertificateTemplate::DESIGN_MODERN_BLUE)
        <div class="absolute inset-x-0 top-0 h-8 bg-blue-700"></div>
        <div class="absolute inset-x-0 bottom-0 h-3 bg-blue-700"></div>
    @endif

    @if ($template['design'] === \App\Models\CertificateTemplate::DESIGN_MINIMAL_ACADEMIC)
        <div class="absolute inset-3 rounded border-4 border-double border-amber-700"></div>
    @endif

    @foreach ($elements as $key => $element)
        @if ($element['visible'])
            <div
                class="certificate-element {{ $element['classes'] }} {{ $layout[$key]['font_family'] }} {{ $layout[$key]['text_align'] }}"
                style="{{ $elementStyle($layout[$key]) }}"
            >
                {{ $element['content'] }}
            </div>
        @endif
    @endforeach

    @if ($layout['issued_on']['enabled'] || $layout['expires_at']['enabled'])
        <div class="absolute inset-x-0 bottom-9 text-center text-xs font-sans">
            @if ($layout['issued_on']['enabled'])
                <div>Issued: {{ $variables['issued_on'] ?? '' }}</div>
            @endif

            @if ($layout['expires_at']['enabled'])
                <div>Valid until: {{ $variables['expires_at'] ?? '' }}</div>
            @endif
        </div>
    @endif

    {{-- <div class="absolute inset-x-0 bottom-4 text-center text-xs text-zinc-500 font-sans">
        {{ $certificate->certificate_number }}
    </div> --}}
</main>

<script>
    window.certificateReady = false;

    document.fonts.ready.then(() => {
        window.certificateReady = true;
    });
</script>
</body>
</html>
