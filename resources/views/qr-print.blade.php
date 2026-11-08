@php
    /** @var \Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models\QrCodeModel $qr */
    /** @var \Modules\LinkPage\Domain\Enums\PrintPaper $paper */
    /** @var list<\Modules\LinkPage\Domain\Enums\PrintPaper> $papers */
    $isA4 = $paper === \Modules\LinkPage\Domain\Enums\PrintPaper::A4;
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('link-page::messages.qr_codes.print.title', ['name' => $qr->name, 'paper' => $paper->label()]) }}</title>
    <style>
        @page { size: {{ $paper->cssSize() }}; margin: 0; }

        :root {
            --sheet-width: {{ $isA4 ? '210mm' : '148mm' }};
            --sheet-height: {{ $isA4 ? '297mm' : '210mm' }};
            --sheet-padding: {{ $isA4 ? '20mm' : '14mm' }};
            --code-side: {{ $paper->codeSideCm() }}cm;
            --title-size: {{ $isA4 ? '30pt' : '21pt' }};
            --tagline-size: {{ $isA4 ? '20pt' : '14pt' }};
            --address-size: {{ $isA4 ? '13pt' : '10pt' }};
            color-scheme: light;
        }

        * { box-sizing: border-box; }

        html, body { margin: 0; padding: 0; }

        body {
            background: #e7e5e4;
            color: #1c1917;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 16px;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: #ffffff;
            border-bottom: 1px solid #d6d3d1;
            font-size: 14px;
        }

        .toolbar p { margin: 0; color: #57534e; max-width: 46ch; }

        .toolbar nav { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }

        .toolbar a, .toolbar button {
            display: inline-flex;
            align-items: center;
            min-height: 40px;
            padding: 0 14px;
            border: 1px solid #a8a29e;
            border-radius: 8px;
            background: #ffffff;
            color: #1c1917;
            font: inherit;
            text-decoration: none;
            cursor: pointer;
        }

        .toolbar a[aria-current="page"] { background: #1c1917; border-color: #1c1917; color: #ffffff; }

        .toolbar button.print { background: #b45309; border-color: #b45309; color: #ffffff; font-weight: 600; }

        .toolbar a:focus-visible, .toolbar button:focus-visible { outline: 3px solid #2563eb; outline-offset: 2px; }

        .stage { padding: 24px 16px; overflow-x: auto; }

        .sheet {
            width: var(--sheet-width);
            height: var(--sheet-height);
            margin: 0 auto;
            padding: var(--sheet-padding);
            background: #ffffff;
            box-shadow: 0 2px 12px rgb(0 0 0 / 0.15);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: {{ $isA4 ? '12mm' : '8mm' }};
            text-align: center;
        }

        .sheet h1 {
            margin: 0;
            font-size: var(--title-size);
            line-height: 1.15;
            overflow-wrap: anywhere;
        }

        .code { width: var(--code-side); height: var(--code-side); }

        .code svg { display: block; width: 100%; height: 100%; }

        .tagline {
            margin: 0;
            font-size: var(--tagline-size);
            line-height: 1.25;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .address {
            margin: 0;
            font-size: var(--address-size);
            color: #44403c;
            font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace;
            letter-spacing: 0.02em;
        }

        @media screen and (max-width: 640px) {
            /* A phone shows the sheet scaled down instead of scrolling sideways */
            .stage { overflow-x: hidden; }
            .sheet { zoom: 0.45; }
        }

        @media screen and (min-width: 641px) and (max-width: 900px) {
            .sheet { zoom: 0.8; }
        }

        @media print {
            body { background: #ffffff; }
            .toolbar { display: none; }
            .stage { padding: 0; overflow: visible; }
            .sheet { box-shadow: none; margin: 0; zoom: 1; }
        }
    </style>
</head>
<body>
    <header class="toolbar">
        <p>{{ __('link-page::messages.qr_codes.print.help') }}</p>
        <nav aria-label="{{ __('link-page::messages.qr_codes.print.paper') }}">
            @foreach ($papers as $option)
                <a href="{{ route('link_page.qr_codes.print', [$qr, $option->value]) }}" @if ($option === $paper) aria-current="page" @endif>{{ $option->label() }}</a>
            @endforeach
            <button type="button" class="print" onclick="window.print()">{{ __('link-page::messages.qr_codes.print.print') }}</button>
        </nav>
    </header>

    <main class="stage">
        <article class="sheet" aria-label="{{ __('link-page::messages.qr_codes.print.sheet', ['paper' => $paper->label()]) }}">
            <h1>{{ $siteName }}</h1>
            <div class="code" role="img" aria-label="{{ __('link-page::messages.qr_codes.print.code_alt', ['address' => $shortUrl]) }}">{!! $svg !!}</div>
            <p class="tagline">{{ $tagline }}</p>
            <p class="address">{{ $shortUrl }}</p>
        </article>
    </main>
</body>
</html>
