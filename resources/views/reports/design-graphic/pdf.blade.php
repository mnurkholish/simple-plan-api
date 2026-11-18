<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #1d2940; font: 10px Arial, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        h1 { margin: 0 0 5px; font-size: 19px; letter-spacing: -.4px; }
        h2 { margin: 16px 0 9px; font-size: 12px; font-weight: 600; }
        .muted { color: #8494ad; }
        .header { border-bottom: 1px solid #dbe5f0; padding-bottom: 12px; }
        .institution { margin-bottom: 10px; }
        .period { margin-bottom: 7px; }
        .cards { display: flex; gap: 9px; align-items: flex-start; }
        .card { flex: 1; min-width: 0; min-height: 58px; border: 1px solid #dce5f0; border-radius: 5px; padding: 8px 9px; }
        .value { font-size: 16px; font-weight: 600; line-height: 1.15; }
        .card .label { margin-top: 5px; color: #8292ab; line-height: 1.3; }
        .panel { border: 1px solid #e7edf4; border-radius: 7px; padding: 14px; break-inside: avoid; }
        .monthly { width: 100%; height: 155px; display: block; }
        .monthly text { font: 9px Arial, sans-serif; fill: #8191aa; }
        .monthly .count { fill: #24334d; }
        .donut-panel { display: flex; align-items: center; gap: 24px; min-height: 146px; background: #fafbfd; }
        .donut { width: 138px; height: 138px; flex: 0 0 138px; }
        .legend { flex: 1; }
        .legend-row { display: flex; align-items: center; margin: 8px 0; gap: 7px; }
        .swatch { width: 9px; height: 9px; display: inline-block; flex: 0 0 9px; }
        .legend-value { margin-left: auto; }
        .type-row { display: flex; align-items: center; gap: 10px; margin: 6px 0; min-height: 16px; break-inside: avoid; page-break-inside: avoid; }
        .type-name { flex: 0 0 27%; overflow-wrap: anywhere; line-height: 1.25; }
        .type-bar { flex: 1; height: 9px; min-width: 0; background: #eef2f7; border-radius: 8px; overflow: hidden; }
        .type-fill { height: 9px; background: #073e94; border-radius: 8px; }
        .type-count { flex: 0 0 28px; text-align: right; }
        .empty { color: #8191aa; font-style: italic; }
    </style>
</head>
<body>
@php
    $statusColors = ['baru' => '#d9dee5', 'ditugaskan' => '#ff9f16', 'menunggu_persetujuan' => '#0a4da2', 'perlu_revisi' => '#e15018', 'selesai' => '#13af83', 'ditolak' => '#ed3038'];
    $monthMax = max([1, ...array_column($charts['request_per_bulan'], 'jumlah')]);
    $statusTotal = array_sum($charts['status_request']);
    $typeMax = max([1, ...array_column($charts['request_per_jenis_desain'], 'jumlah')]);
@endphp

<div class="header">
    <h1>Laporan Desain Grafis</h1>
    <div class="institution muted">RS Citra Husada Jember</div>
    <div class="period"><span class="muted">Periode:</span> {{ $period }}</div>
    <div><span class="muted">Diunduh:</span> {{ $downloadedAt }}, {{ $downloadedBy }}</div>
</div>

<h2>Rekap</h2>
<div class="cards">
    <div class="card"><div class="value">{{ $summary['jumlah_request_masuk'] }}</div><div class="label">Request Masuk</div></div>
    <div class="card"><div class="value">{{ $summary['jumlah_request_selesai'] }}</div><div class="label">Request Selesai</div></div>
    <div class="card"><div class="value">{{ $summary['jumlah_request_ditolak'] }}</div><div class="label">Request Ditolak</div></div>
    <div class="card"><div class="value">{{ number_format($summary['rata_rata_waktu_penyelesaian'], 2, ',', '.') }} <small>hari</small></div><div class="label">Rata-rata Waktu Penyelesaian</div></div>
</div>

<h2>Request per Bulan</h2>
<div class="panel">
    <svg class="monthly" viewBox="0 0 500 155" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Request per bulan">
        @foreach ($charts['request_per_bulan'] as $index => $month)
            @php
                $slot = 500 / max(1, count($charts['request_per_bulan']));
                $width = min(26, $slot * .64);
                $height = 105 * $month['jumlah'] / $monthMax;
                $x = $index * $slot + ($slot - $width) / 2;
            @endphp
            <rect x="{{ $x }}" y="{{ 120 - $height }}" width="{{ $width }}" height="{{ $height }}" fill="#073e94" rx="2"/>
            <text class="count" x="{{ $index * $slot + $slot / 2 }}" y="{{ 114 - $height }}" text-anchor="middle">{{ $month['jumlah'] }}</text>
            <text x="{{ $index * $slot + $slot / 2 }}" y="140" text-anchor="middle">{{ \Carbon\Carbon::createFromFormat('!Y-m', $month['bulan'])->translatedFormat('M y') }}</text>
        @endforeach
    </svg>
</div>

<h2>Status Request</h2>
<div class="panel donut-panel">
    <svg class="donut" viewBox="0 0 138 138" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Status request">
        <circle cx="69" cy="69" r="48" fill="none" stroke="#eef2f7" stroke-width="17"/>
        @php $offset = 0; @endphp
        @foreach ($charts['status_request'] as $key => $count)
            @if ($count > 0 && $statusTotal > 0)
                @php $length = 301.6 * $count / $statusTotal; @endphp
                <circle cx="69" cy="69" r="48" fill="none" stroke="{{ $statusColors[$key] ?? '#8494ad' }}" stroke-width="17" stroke-dasharray="{{ $length }} {{ 301.6 - $length }}" stroke-dashoffset="{{ -$offset }}" transform="rotate(-90 69 69)"/>
                @php $offset += $length; @endphp
            @endif
        @endforeach
        <text x="69" y="68" text-anchor="middle" font-size="20" font-weight="bold" fill="#1d2940">{{ $statusTotal }}</text>
        <text x="69" y="83" text-anchor="middle" font-size="10" fill="#8a9ab0">Request</text>
    </svg>
    <div class="legend">
        @foreach ($charts['status_request'] as $key => $count)
            <div class="legend-row"><span class="swatch" style="background:{{ $statusColors[$key] ?? '#8494ad' }}"></span><span>{{ ucwords(str_replace('_', ' ', $key)) }}</span><span class="legend-value">{{ $count }}</span></div>
        @endforeach
    </div>
</div>

<h2>Request per Jenis Desain</h2>
@forelse ($charts['request_per_jenis_desain'] as $row)
    <div class="type-row">
        <div class="type-name">{{ $row['jenis_desain'] }}</div>
        <div class="type-bar"><div class="type-fill" style="width:{{ 100 * $row['jumlah'] / $typeMax }}%"></div></div>
        <div class="type-count">{{ $row['jumlah'] }}</div>
    </div>
@empty
    <div class="empty">Tidak ada request</div>
@endforelse
</body>
</html>
