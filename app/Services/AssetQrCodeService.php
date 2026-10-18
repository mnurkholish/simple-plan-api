<?php

namespace App\Services;

use App\Models\Asset;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

class AssetQrCodeService
{
    public function generateSvg(Asset $asset): string
    {
        $baseUrl = rtrim(
            config('inventory.scan_base_url'),
            '/'
        );

        $url = $baseUrl.'/'.$asset->asset_uid;

        $qrCode = new QrCode(
            data: $url,
            size: 300,
            margin: 10,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium
        );

        $writer = new SvgWriter;

        return $writer->write($qrCode)->getString();
    }
}
