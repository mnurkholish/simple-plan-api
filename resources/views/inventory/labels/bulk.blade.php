<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    @include('inventory.labels.partials.styles', ['pageMode' => 'a4'])
</head>

<body>
    @foreach ($pages as $page)
        <div class="label-page">
            @foreach ($page as $label)
                <div class="asset-label">
                    @include('inventory.labels.partials.content', $label)
                </div>
            @endforeach
        </div>
    @endforeach
</body>
</html>
