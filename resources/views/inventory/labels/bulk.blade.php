<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    @include('inventory.labels.partials.styles', ['pageMode' => 'bulk'])
</head>

<body>
    @foreach ($labels as $label)
        <div class="label-page">
            <div class="asset-label">
                @include('inventory.labels.partials.content', $label)
            </div>
        </div>
    @endforeach
    @include('inventory.labels.partials.fit')
</body>
</html>
