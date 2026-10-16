@php
    $widthMm = $widthMm ?? 90;
    $heightMm = $heightMm ?? 50;

    // Adjust QR size based on label dimensions.
    $qrSizeMm = min(
        26,
        max(18, min($widthMm * 0.28, $heightMm * 0.47))
    );

    $isCompact = $heightMm <= 45;
    $pageMode = $pageMode ?? 'single';
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
        width: {{ $widthMm }}mm;
        height: {{ $heightMm }}mm;
        padding: {{ $isCompact ? 2 : 3 }}mm;
        overflow: hidden;
    }

    .header {
        border-bottom: 1px solid #ddd;
        padding-bottom: {{ $isCompact ? 1 : 2 }}mm;
    }

    .inventory-number {
        font-size: {{ $isCompact ? 10 : 11 }}pt;
        overflow-wrap: anywhere;
    }

    .inventory-type {
        margin-top: 1mm;
        font-size: 7pt;
        color: #777;
        letter-spacing: 0.5px;
    }

    .content {
        display: grid;
        grid-template-columns:
            minmax(0, 1fr) {{ $qrSizeMm }}mm;
        gap: 2mm;
        margin-top: {{ $isCompact ? 2 : 3 }}mm;
    }

    .field {
        margin-bottom: {{ $isCompact ? 1.5 : 2.5 }}mm;
    }

    .field-label {
        font-size: 7pt;
        color: #777;
        letter-spacing: 0.4px;
        margin-bottom: 1mm;
    }

    .field-value {
        font-size: {{ $isCompact ? 8 : 9 }}pt;
        line-height: 1.2;
        overflow-wrap: anywhere;
    }

    .model {
        margin-top: 1mm;
    }

    .qr-code {
        width: {{ $qrSizeMm }}mm;
        height: {{ $qrSizeMm }}mm;
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
