<div class="letterhead">
    <div class="letterhead-main">
        <img class="hospital-logo" src="{{ $logoDataUri }}" alt="Logo RS Citra Husada">
        <div class="letterhead-title">KARTU INVENTARIS<br>RS CITRA HUSADA</div>
    </div>
    <div class="letterhead-address">Jl. Teratai No. 22 Jember 0331-486200</div>
</div>

<div class="content">
    <div class="information">
        <div class="field">
            <span class="field-label">Nomor</span><span class="field-separator">:</span><span class="field-value">{{ $inventoryNumber }}</span>
        </div>
        <div class="field">
            <span class="field-label">Nama</span><span class="field-separator">:</span><span class="field-value">{{ $itemName }}</span>
        </div>
        <div class="field">
            <span class="field-label">Lokasi</span><span class="field-separator">:</span><span class="field-value">{{ $roomName }}</span>
        </div>
        <div class="field">
            <span class="field-label">Kondisi</span><span class="field-separator">:</span><span class="field-value">{{ $conditionText }}</span>
        </div>
    </div>

    <div class="qr-block">
        <div class="qr-code">
            {!! $qrSvg !!}
        </div>
        <div class="qr-caption">Pindai QR untuk mengakses detail data inventaris</div>
    </div>
</div>
