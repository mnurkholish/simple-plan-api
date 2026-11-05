@php
    $widthMm = $widthMm ?? 50;
    $heightMm = $heightMm ?? 60;
    $pageMode = $pageMode ?? 'single';

    $scale = min($widthMm / 50, $heightMm / 60);
    $paddingMm = round(max(1, min(3, $heightMm * 0.04)), 2);
    $qrSizeMm = round(min(28, $widthMm * 0.46, $heightMm * 0.31), 2);
    $fontPt = round(max(5, min(7.5, 7.5 * $scale)), 2);
    $titlePt = round(max(5.2, min(7, 7 * $scale)), 2);
    $addressPt = round(max(4.5, min(7.3, 7.3 * $scale)), 2);
@endphp

<style>
    @page {
        size: {{ $pageMode === 'a4' ? 'A4 portrait' : $widthMm.'mm '.$heightMm.'mm' }};
        margin: {{ $pageMode === 'a4' ? '10mm' : '0' }};
    }

    * { box-sizing: border-box; }

    html, body {
        @if ($pageMode === 'single')
            width: {{ $widthMm }}mm;
            height: {{ $heightMm }}mm;
        @endif
        margin: 0;
        padding: 0;
    }

    body {
        font-family: Arial, sans-serif;
        color: #111;
        background: #fff;
    }

    .asset-label {
        --text-fit: 1;
        width: {{ $widthMm }}mm;
        height: {{ $heightMm }}mm;
        padding: {{ $paddingMm }}mm;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .letterhead {
        flex: none;
        padding-bottom: calc(0.8mm * var(--text-fit));
        border-bottom: 0.7mm solid #111;
        text-align: center;
    }

    .letterhead-main {
        display: grid;
        grid-template-columns: {{ round(10 * $scale, 2) }}mm minmax(0, 1fr);
        align-items: center;
        column-gap: 0.8mm;
    }

    .hospital-logo {
        flex: none;
        width: {{ round(10 * $scale, 2) }}mm;
        height: auto;
        display: block;
    }

    .letterhead-title {
        font-family: 'Times New Roman', Times, serif;
        font-size: calc({{ $titlePt }}pt * var(--text-fit));
        font-weight: 700;
        line-height: 1.1;
        transform: translateX(-2.5mm);
    }

    .letterhead-address {
        margin-top: calc(0.25mm * var(--text-fit));
        font-family: 'Times New Roman', Times, serif;
        font-size: calc({{ $addressPt }}pt * var(--text-fit));
        line-height: 1.1;
        white-space: nowrap;
    }

    .content {
        flex: 1;
        min-height: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        overflow: hidden;
        padding-top: calc(1.5mm * var(--text-fit));
    }

    .information {
        flex: none;
        width: 100%;
        min-width: 0;
        overflow: hidden;
    }

    .field {
        display: grid;
        grid-template-columns: {{ round(11.5 * $scale, 2) }}mm 1.5mm minmax(0, 1fr);
        column-gap: 0.4mm;
        margin-bottom: calc(0.35mm * var(--text-fit));
        font-size: calc({{ $fontPt }}pt * var(--text-fit));
        line-height: 1.13;
    }

    .field:last-child { margin-bottom: 0; }
    .field-label { font-weight: 700; }
    .field-value { min-width: 0; overflow-wrap: anywhere; }

    .qr-block {
        flex: none;
        width: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.8mm;
        margin-top: auto;
        margin-bottom: auto;
    }

    .qr-code {
        flex: none;
        width: {{ $qrSizeMm }}mm;
        height: {{ $qrSizeMm }}mm;
    }

    .qr-code svg {
        display: block;
        width: 100%;
        height: 100%;
    }

    .qr-caption {
        width: 100%;
        font-size: calc(5.4pt * var(--text-fit));
        line-height: 1.1;
        text-align: center;
    }

    @if ($pageMode === 'a4')
        .label-page {
            display: grid;
            grid-template-columns: repeat({{ $columns }}, {{ $widthMm }}mm);
            grid-auto-rows: {{ $heightMm }}mm;
            gap: {{ $gapMm }}mm;
            width: 190mm;
            break-after: page;
            page-break-after: always;
        }

        .label-page:last-child {
            break-after: auto;
            page-break-after: auto;
        }

        .label-page .asset-label {
            border: 0.25mm solid #666;
            break-inside: avoid;
            page-break-inside: avoid;
        }
    @endif
</style>
