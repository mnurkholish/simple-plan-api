<div class="header">
    <div class="inventory-number">
        {{ $inventoryNumber }}
    </div>

    <div class="inventory-type">
        {{ $inventoryType }}
    </div>
</div>

<div class="content">

    <div class="information">
        <div class="field">
            <div class="field-label">NAMA BARANG</div>
            <div class="field-value">
                {{ $itemName }}

                @if ($asset->model_specification)
                    <div class="model">
                        {{ $asset->model_specification }}
                    </div>
                @endif
            </div>
        </div>

        <div class="field">
            <div class="field-label">
                LOKASI RUANGAN
            </div>
            <div class="field-value">
                {{ $roomName }}
            </div>
        </div>

        <div class="field">
            <div class="field-label">KONDISI</div>
            <div class="field-value">
                {{ $conditionText }}
            </div>
        </div>
    </div>

    <div class="qr-code">
        {!! $qrSvg !!}
    </div>

</div>
