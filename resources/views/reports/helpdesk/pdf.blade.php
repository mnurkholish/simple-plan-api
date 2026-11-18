<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #1d2940; font: 10px Arial, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        h1 { margin: 0 0 5px; font-size: 19px; letter-spacing: -.4px; }
        h2 { margin: 18px 0 10px; font-size: 12px; font-weight: 600; }
        .muted { color: #8494ad; }
        .header { border-bottom: 1px solid #dbe5f0; padding-bottom: 12px; }
        .institution { margin-bottom: 10px; }
        .meta { display: flex; gap: 18px; margin-bottom: 7px; }
        .cards { display: flex; gap: 8px; align-items: flex-start; }
        .card { flex: 1; min-width: 0; min-height: 58px; border: 1px solid #dce5f0; border-radius: 5px; padding: 8px 7px; }
        .value { font-size: 16px; font-weight: 600; line-height: 1.15; }
        .card .label { margin-top: 5px; color: #8292ab; line-height: 1.3; }
        .panel { border: 1px solid #e7edf4; border-radius: 7px; padding: 16px; break-inside: avoid; }
        .monthly { width: 100%; height: 170px; display: block; }
        .monthly text { font: 9px Arial, sans-serif; fill: #8191aa; }
        .monthly .count { fill: #24334d; }
        .donut-panel { display: flex; align-items: center; gap: 24px; min-height: 156px; background: #fafbfd; }
        .donut { width: 136px; height: 136px; flex: 0 0 136px; }
        .legend { flex: 1; }
        .legend-row { display: flex; align-items: center; margin: 8px 0; gap: 7px; }
        .swatch { width: 9px; height: 9px; display: inline-block; flex: 0 0 9px; }
        .legend-value { margin-left: auto; }
        .second-page { break-before: page; page-break-before: always; }
        .category-group { margin-top: 13px; }
        .category-group h3 { font-size: 10px; font-weight: 600; margin: 0 0 6px; }
        .category-row { display: flex; align-items: center; gap: 10px; margin: 7px 0; min-height: 17px; break-inside: avoid; page-break-inside: avoid; }
        .category-name { flex: 0 0 27%; overflow-wrap: anywhere; line-height: 1.3; }
        .category-bar { flex: 1; min-width: 0; height: 10px; background: #eef2f7; border-radius: 8px; overflow: hidden; }
        .category-fill { height: 10px; background: #073e94; border-radius: 8px; }
        .category-count { flex: 0 0 28px; text-align: right; }
        .empty { color: #8191aa; font-style: italic; }
    </style>
</head>
<body>
@php
    $statusColors = ['baru' => '#d9dee5', 'diklasifikasi' => '#ff9f16', 'diproses' => '#0a4da2', 'eskalasi' => '#e15018', 'terselesaikan' => '#13af83', 'ditutup' => '#6a7a94', 'ditolak' => '#ed3038'];
    $monthMax = max(1, ...array_column($charts['tiket_per_bulan'], 'jumlah'));
    $statusTotal = array_sum($charts['status_tiket']);
    $slaTotal = array_sum($charts['sla']);
    $categoryGroups = $service === 'Semua'
        ? [
            'Kategori Sarpras' => $charts['tiket_per_kategori']['sarpras'],
            'Kategori TIK' => $charts['tiket_per_kategori']['tik'],
        ]
        : [$service === 'TIK' ? 'Kategori TIK' : 'Kategori Sarpras' => $charts['tiket_per_kategori'][$service === 'TIK' ? 'tik' : 'sarpras']];
    $categoryMax = max(1, ...array_merge([0], array_column(array_merge(...array_values($categoryGroups)), 'jumlah')));
@endphp

<div class="header">
    <h1>Laporan Helpdesk</h1>
    <div class="institution muted">RS Citra Husada Jember</div>
    <div class="meta"><span><span class="muted">Periode:</span> {{ $period }}</span><span><span class="muted">Jenis Layanan:</span> {{ $service }}</span></div>
    <div><span class="muted">Diunduh:</span> {{ $downloadedAt }}, {{ $downloadedBy }}</div>
</div>

<h2>Rekap</h2>
<div class="cards">
    <div class="card"><div class="value">{{ $summary['jumlah_tiket_masuk'] }}</div><div class="label">Tiket masuk</div></div>
    <div class="card"><div class="value">{{ $summary['jumlah_tiket_selesai'] }}</div><div class="label">Tiket selesai</div></div>
    <div class="card"><div class="value">{{ $summary['jumlah_tiket_ditolak'] }}</div><div class="label">Tiket ditolak</div></div>
    <div class="card"><div class="value">{{ number_format($summary['rata_rata_waktu_penyelesaian'], 2, ',', '.') }} <small>Jam</small></div><div class="label">Rata-rata waktu penyelesaian</div></div>
    <div class="card"><div class="value">{{ number_format($summary['persentase_sla_tercapai'], 2, ',', '.') }}<small>%</small></div><div class="label">SLA Tercapai</div></div>
</div>

<h2>Jumlah Tiket per Bulan</h2>
<div class="panel">
    <svg class="monthly" viewBox="0 0 500 170" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Jumlah tiket per bulan">
        @foreach ($charts['tiket_per_bulan'] as $index => $month)
            @php
                $slot = 500 / max(1, count($charts['tiket_per_bulan']));
                $width = min(26, $slot * .64);
                $height = 116 * $month['jumlah'] / $monthMax;
                $x = $index * $slot + ($slot - $width) / 2;
            @endphp
            <rect x="{{ $x }}" y="{{ 132 - $height }}" width="{{ $width }}" height="{{ $height }}" fill="#073e94" rx="2"/>
            <text class="count" x="{{ $index * $slot + $slot / 2 }}" y="{{ 126 - $height }}" text-anchor="middle">{{ $month['jumlah'] }}</text>
            <text x="{{ $index * $slot + $slot / 2 }}" y="151" text-anchor="middle">{{ \Carbon\Carbon::createFromFormat('!Y-m', $month['bulan'])->translatedFormat('M y') }}</text>
        @endforeach
    </svg>
</div>

<h2>Status Tiket</h2>
<div class="panel donut-panel">
    <svg class="donut" viewBox="0 0 136 136" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Distribusi status tiket">
        <circle cx="68" cy="68" r="48" fill="none" stroke="#eef2f7" stroke-width="17"/>
        @php $offset = 0; @endphp
        @foreach ($charts['status_tiket'] as $key => $count)
            @if ($count > 0 && $statusTotal > 0)
                @php $length = 301.6 * $count / $statusTotal; @endphp
                <circle cx="68" cy="68" r="48" fill="none" stroke="{{ $statusColors[$key] ?? '#8494ad' }}" stroke-width="17" stroke-dasharray="{{ $length }} {{ 301.6 - $length }}" stroke-dashoffset="{{ -$offset }}" transform="rotate(-90 68 68)"/>
                @php $offset += $length; @endphp
            @endif
        @endforeach
        <text x="68" y="67" text-anchor="middle" font-size="20" font-weight="bold" fill="#1d2940">{{ $statusTotal }}</text>
        <text x="68" y="82" text-anchor="middle" font-size="10" fill="#8a9ab0">Tiket</text>
    </svg>
    <div class="legend">
        @foreach ($charts['status_tiket'] as $key => $count)
            <div class="legend-row"><span class="swatch" style="background:{{ $statusColors[$key] ?? '#8494ad' }}"></span><span>{{ ucfirst(str_replace('_', ' ', $key)) }}</span><span class="legend-value">{{ $count }}</span></div>
        @endforeach
    </div>
</div>

<div class="second-page">
    <h2 style="margin-top:0">Ketercapaian SLA</h2>
    <div class="panel donut-panel">
        <svg class="donut" viewBox="0 0 136 136" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Ketercapaian SLA">
            <circle cx="68" cy="68" r="48" fill="none" stroke="#eef2f7" stroke-width="17"/>
            @php $slaOffset = 0; @endphp
            @foreach (['tercapai' => '#073e94', 'terlambat' => '#3ebcf1'] as $key => $color)
                @if ($charts['sla'][$key] > 0 && $slaTotal > 0)
                    @php $length = 301.6 * $charts['sla'][$key] / $slaTotal; @endphp
                    <circle cx="68" cy="68" r="48" fill="none" stroke="{{ $color }}" stroke-width="17" stroke-dasharray="{{ $length }} {{ 301.6 - $length }}" stroke-dashoffset="{{ -$slaOffset }}" transform="rotate(-90 68 68)"/>
                    @php $slaOffset += $length; @endphp
                @endif
            @endforeach
            <text x="68" y="67" text-anchor="middle" font-size="20" font-weight="bold" fill="#1d2940">{{ $slaTotal }}</text>
            <text x="68" y="82" text-anchor="middle" font-size="10" fill="#8a9ab0">Tiket</text>
        </svg>
        <div class="legend">
            @foreach (['tercapai' => '#073e94', 'terlambat' => '#3ebcf1'] as $key => $color)
                <div class="legend-row"><span class="swatch" style="background:{{ $color }}"></span><span>{{ ucfirst($key) }}</span><span class="legend-value">{{ $charts['sla'][$key] }}</span></div>
            @endforeach
        </div>
    </div>

    <h2>Tiket per Kategori</h2>
    @foreach ($categoryGroups as $group => $rows)
        <div class="category-group">
            <h3>{{ $group }}</h3>
            @forelse ($rows as $row)
                <div class="category-row">
                    <div class="category-name">{{ $row['kategori'] }}</div>
                    <div class="category-bar"><div class="category-fill" style="width:{{ 100 * $row['jumlah'] / $categoryMax }}%"></div></div>
                    <div class="category-count">{{ $row['jumlah'] }}</div>
                </div>
            @empty
                <div class="empty">Tidak ada tiket</div>
            @endforelse
        </div>
    @endforeach
</div>
</body>
</html>
