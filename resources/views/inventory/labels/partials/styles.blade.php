@php
    $widthMm = $widthMm ?? 90;
    $heightMm = $heightMm ?? 50;
    $pageMode = $pageMode ?? 'single';

    $scale = min($widthMm / 90, $heightMm / 50);
    $paddingMm = round(max(1, min(3, $heightMm * 0.055)), 2);
    $qrSizeMm = round(min(24, $widthMm * 0.25, $heightMm * 0.5), 2);
    $contentGapMm = round(max(0.8, min(2, 2 * $scale)), 2);
    $inventoryFontPt = round(max(6, min(11, 11 * $scale)), 2);
    $typeFontPt = round(max(4.5, min(7, 7 * $scale)), 2);
    $labelFontPt = round(max(4.5, min(7, 7 * $scale)), 2);
    $valueFontPt = round(max(5.2, min(9, 9 * $scale)), 2);
@endphp

<style>
    @page {
        size: {{ $pageMode === 'a4' ? 'A4 portrait' : $widthMm.'mm '.$heightMm.'mm' }};
        margin: {{ $pageMode === 'a4' ? '10mm' : '0' }};
    }

    * {
        box-sizing: border-box;
    }

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
        color: #222;
        background: #fff;
    }

    .asset-label {
        --text-fit: 1;
        width: {{ $widthMm }}mm;
        height: {{ $heightMm }}mm;
        border: 0.2mm solid #777;
        padding: {{ $paddingMm }}mm;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .header {
        flex: none;
        border-bottom: 1px solid #ddd;
        padding-bottom: calc({{ round(max(0.4, 1.5 * $scale), 2) }}mm * var(--text-fit));
    }

    .inventory-number {
        font-size: calc({{ $inventoryFontPt }}pt * var(--text-fit));
        font-weight: 700;
        line-height: 1.05;
        overflow-wrap: anywhere;
    }

    .inventory-type {
        margin-top: calc({{ round(max(0.2, $scale), 2) }}mm * var(--text-fit));
        font-size: calc({{ $typeFontPt }}pt * var(--text-fit));
        line-height: 1.05;
        color: #777;
        letter-spacing: 0.3px;
    }

    .content {
        flex: 1;
        min-height: 0;
        display: grid;
        grid-template-columns: minmax(0, 1fr) {{ $qrSizeMm }}mm;
        grid-template-rows: minmax(0, 1fr);
        gap: {{ $contentGapMm }}mm;
        margin-top: calc({{ round(max(0.5, 2 * $scale), 2) }}mm * var(--text-fit));
    }

    .information {
        min-width: 0;
        min-height: 0;
        overflow: hidden;
    }

    .field {
        margin-bottom: calc({{ round(max(0.35, 1.8 * $scale), 2) }}mm * var(--text-fit));
    }

    .field:last-child {
        margin-bottom: 0;
    }

    .field-label {
        font-size: calc({{ $labelFontPt }}pt * var(--text-fit));
        line-height: 1.05;
        color: #777;
        letter-spacing: 0.2px;
        margin-bottom: calc({{ round(max(0.1, 0.5 * $scale), 2) }}mm * var(--text-fit));
    }

    .field-value {
        font-size: calc({{ $valueFontPt }}pt * var(--text-fit));
        line-height: 1.1;
        overflow-wrap: anywhere;
    }

    .model {
        margin-top: calc({{ round(max(0.1, 0.6 * $scale), 2) }}mm * var(--text-fit));
        font-size: 0.92em;
        color: #555;
    }

    .qr-code {
        width: {{ $qrSizeMm }}mm;
        height: {{ $qrSizeMm }}mm;
        align-self: center;
    }

    .qr-code svg {
        display: block;
        width: 100%;
        height: 100%;
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
            break-inside: avoid;
            page-break-inside: avoid;
        }
    @endif
</style>
