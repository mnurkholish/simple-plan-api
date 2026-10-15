<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <style>
        @page {
            size: 90mm 50mm;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            color: #222;
            background: #fff;
        }

        .asset-label {
            width: 90mm;
            height: 50mm;
            padding: 3mm;
            overflow: hidden;
        }

        .header {
            border-bottom: 1px solid #ddd;
            padding-bottom: 2mm;
        }

        .inventory-number {
            font-size: 11pt;
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
            grid-template-columns: minmax(0, 1fr) 23mm;
            gap: 2mm;
            margin-top: 3mm;
        }

        .field {
            margin-bottom: 2.5mm;
        }

        .field-label {
            font-size: 7pt;
            color: #777;
            letter-spacing: 0.4px;
            margin-bottom: 1mm;
        }

        .field-value {
            font-size: 9pt;
            line-height: 1.2;
            overflow-wrap: anywhere;
        }

        .model {
            margin-top: 1mm;
        }

        .qr-code {
            width: 23mm;
            height: 23mm;
        }

        .qr-code svg {
            display: block;
            width: 100%;
            height: 100%;
        }
    </style>
</head>

<body>
    <div class="asset-label">

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
                    <div class="field-label">LOKASI RUANGAN</div>
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
    </div>
</body>
</html>