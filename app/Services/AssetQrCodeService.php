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
        $qrCode = new QrCode(
            data: $asset->asset_uid,
            size: 300,
            margin: 10,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium
        );

        $writer = new SvgWriter;

        return $writer->write($qrCode)->getString();
    }
}
