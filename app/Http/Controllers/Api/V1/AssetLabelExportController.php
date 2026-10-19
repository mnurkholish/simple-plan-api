<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExportAssetLabelsRequest;
use App\Services\AssetLabelExportService;
use Illuminate\Http\Response;

class AssetLabelExportController extends Controller
{
    public function store(ExportAssetLabelsRequest $request, AssetLabelExportService $service): Response
    {
        $data = $request->validated();

        return response($service->generatePdf(
            $data,
            (float) $data['width_mm'],
            (float) $data['height_mm'],
        ), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="asset-labels-a4.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
