<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #0F172A;
            font-size: 11px;
            line-height: 1.45;
            background: #FFFFFF;
        }
        .header {
            padding-bottom: 14px;
            border-bottom: 1.5px solid #CBD5E1;
            margin-bottom: 18px;
        }
        .header-title {
            margin: 0 0 2px;
            font-size: 20px;
            font-weight: 700;
            color: #00338D;
            letter-spacing: -0.01em;
        }
        .header-subtitle {
            margin: 0 0 10px;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
        }
        .meta-row {
            margin: 3px 0;
            font-size: 10.5px;
            color: #334155;
        }
        .meta-label {
            font-weight: 700;
            color: #0F172A;
        }
        .meta-sep {
            display: inline-block;
            margin: 0 8px;
            color: #CBD5E1;
        }
        .section {
            margin-bottom: 18px;
        }
        .section-title {
            margin: 0 0 10px;
            font-size: 13px;
            font-weight: 700;
            color: #0F172A;
        }
        .summary-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px 0;
            margin: 0 -10px;
        }
        .summary-card {
            width: 33.33%;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            padding: 12px 14px;
            vertical-align: top;
        }
        .summary-label {
            font-size: 10.5px;
            color: #64748B;
            margin-bottom: 6px;
        }
        .summary-value {
            font-size: 20px;
            font-weight: 700;
            color: #00338D;
            line-height: 1.15;
        }
        .card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            padding: 14px 16px;
        }
        .avoid-break {
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .donut-layout {
            width: 100%;
            border-collapse: collapse;
        }
        .donut-cell {
            width: 200px;
            vertical-align: middle;
            text-align: center;
            padding-right: 16px;
        }
        .donut-cell svg {
            display: inline-block;
            width: 170px;
            height: 170px;
        }
        .legend-cell {
            vertical-align: middle;
        }
        .legend-table {
            width: 100%;
            border-collapse: collapse;
        }
        .legend-table tr {
            border-bottom: 1px solid #F1F5F9;
        }
        .legend-table tr:last-child {
            border-bottom: none;
        }
        .legend-table td {
            padding: 6px 2px;
            vertical-align: middle;
        }
        .legend-swatch {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 2px;
            margin-right: 8px;
            vertical-align: middle;
        }
        .legend-name {
            color: #0F172A;
            font-weight: 500;
        }
        .legend-count {
            text-align: right;
            font-weight: 700;
            color: #0F172A;
            white-space: nowrap;
        }
        .bar-list {
            width: 100%;
        }
        .bar-item {
            margin-bottom: 10px;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .bar-item:last-child {
            margin-bottom: 0;
        }
        .bar-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .bar-label {
            font-size: 10.5px;
            font-weight: 600;
            color: #0F172A;
            text-align: left;
        }
        .bar-value {
            font-size: 10.5px;
            font-weight: 700;
            color: #00338D;
            text-align: right;
            white-space: nowrap;
        }
        .bar-track {
            width: 100%;
            height: 10px;
            background: #EDF2F7;
            border-radius: 999px;
            overflow: hidden;
        }
        .bar-fill {
            height: 10px;
            background: #00338D;
            border-radius: 999px;
        }
        .empty-state {
            text-align: center;
            color: #64748B;
            padding: 16px 8px;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <header class="header">
        <h1 class="header-title">{{ $title }}</h1>
        <p class="header-subtitle">{{ $subtitle ?? 'RS Citra Husada Jember' }}</p>

        <div class="meta-row">
            <span class="meta-label">Klasifikasi:</span> {{ $filters['Klasifikasi'] ?? 'Semua' }}
            <span class="meta-sep">|</span>
            <span class="meta-label">Status:</span> {{ $filters['Status'] ?? 'Semua' }}
            <span class="meta-sep">|</span>
            <span class="meta-label">Lokasi ruangan:</span> {{ $filters['Lokasi ruangan'] ?? 'Semua' }}
        </div>
        <div class="meta-row">
            <span class="meta-label">Diunduh:</span> {{ $downloaded_at ?? '-' }}
        </div>
    </header>

    <section class="section avoid-break">
        <h2 class="section-title">Rekap</h2>
        <table class="summary-grid">
            <tr>
                <td class="summary-card">
                    <div class="summary-label">Total aset</div>
                    <div class="summary-value">{{ number_format((int) ($summary['total_aset'] ?? 0), 0, ',', '.') }}</div>
                </td>
                <td class="summary-card">
                    <div class="summary-label">Aset rusak</div>
                    <div class="summary-value">{{ number_format((int) ($summary['total_rusak'] ?? 0), 0, ',', '.') }}</div>
                </td>
                <td class="summary-card">
                    <div class="summary-label">Aset dipinjam</div>
                    <div class="summary-value">{{ number_format((int) ($summary['total_dipinjam'] ?? 0), 0, ',', '.') }}</div>
                </td>
            </tr>
        </table>
    </section>

    <section class="section avoid-break">
        <h2 class="section-title">Aset per Klasifikasi</h2>
        <div class="card">
            <table class="donut-layout">
                <tr>
                    <td class="donut-cell">
                        {!! $charts['per_klasifikasi_donut_svg'] ?? '' !!}
                    </td>
                    <td class="legend-cell">
                        @if (!empty($charts['per_klasifikasi_legend']))
                            <table class="legend-table">
                                @foreach ($charts['per_klasifikasi_legend'] as $item)
                                    <tr>
                                        <td>
                                            <span class="legend-swatch" style="background: {{ $item['color'] }};"></span>
                                            <span class="legend-name">{{ $item['label'] }}</span>
                                        </td>
                                        <td class="legend-count">{{ number_format((int) $item['count'], 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        @else
                            <div class="empty-state">Tidak ada data klasifikasi aset.</div>
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    </section>

    <section class="section avoid-break">
        <h2 class="section-title">Aset per Kondisi</h2>
        <div class="card">
            @php
                $conditionItems = $charts['per_kondisi'] ?? [];
                $maxCondition = max(1, ...array_map(fn (array $row): int => (int) ($row['count'] ?? 0), $conditionItems ?: [['count' => 0]]));
            @endphp
            @if (!empty($conditionItems))
                <div class="bar-list">
                    @foreach ($conditionItems as $item)
                        @php
                            $count = (int) ($item['count'] ?? 0);
                            $widthPct = $count > 0 ? max(2, round(($count / $maxCondition) * 100, 2)) : 0;
                        @endphp
                        <div class="bar-item">
                            <table class="bar-header">
                                <tr>
                                    <td class="bar-label">{{ $item['label'] }}</td>
                                    <td class="bar-value">{{ number_format($count, 0, ',', '.') }}</td>
                                </tr>
                            </table>
                            <div class="bar-track">
                                @if ($widthPct > 0)
                                    <div class="bar-fill" style="width: {{ $widthPct }}%;"></div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="empty-state">Tidak ada data kondisi aset.</div>
            @endif
        </div>
    </section>

    <section class="section">
        <h2 class="section-title">Aset per Lokasi Ruangan</h2>
        <div class="card">
            @php
                $locationItems = $charts['per_lokasi'] ?? [];
                $maxLocation = max(1, ...array_map(fn (array $row): int => (int) ($row['count'] ?? 0), $locationItems ?: [['count' => 0]]));
            @endphp
            @if (!empty($locationItems))
                <div class="bar-list">
                    @foreach ($locationItems as $item)
                        @php
                            $count = (int) ($item['count'] ?? 0);
                            $widthPct = $count > 0 ? max(2, round(($count / $maxLocation) * 100, 2)) : 0;
                        @endphp
                        <div class="bar-item">
                            <table class="bar-header">
                                <tr>
                                    <td class="bar-label">{{ $item['label'] }}</td>
                                    <td class="bar-value">{{ number_format($count, 0, ',', '.') }}</td>
                                </tr>
                            </table>
                            <div class="bar-track">
                                @if ($widthPct > 0)
                                    <div class="bar-fill" style="width: {{ $widthPct }}%;"></div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="empty-state">Tidak ada data lokasi ruangan.</div>
            @endif
        </div>
    </section>
</body>
</html>
