<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; color: #243447; font-size: 11px; }
        h1 { margin: 0 0 18px; font-size: 21px; color: #173b5e; }
        h2 { margin: 22px 0 9px; font-size: 14px; color: #173b5e; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 10px; border-bottom: 1px solid #dce4ea; text-align: left; }
        th { width: 38%; background: #edf3f7; }
        .chart { break-inside: avoid; margin: 16px 0 22px; }
        .chart svg { display: block; width: 100%; max-height: 320px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>

    @if (!empty($filters))
        <h2>Filter dan Periode</h2>
        <table>
            @foreach ($filters as $label => $value)
                <tr><th>{{ $label }}</th><td>{{ $value ?? 'Semua' }}</td></tr>
            @endforeach
        </table>
    @endif

    @if (!empty($summary))
        <h2>Rekap</h2>
        <table>
            @foreach ($summary as $label => $value)
                <tr><th>{{ $label }}</th><td>{{ $value ?? '—' }}</td></tr>
            @endforeach
        </table>
    @endif

    @foreach ($charts ?? [] as $chart)
        <section class="chart">
            <h2>{{ $chart['title'] }}</h2>
            {!! $chart['svg'] !!}
        </section>
    @endforeach
</body>
</html>
