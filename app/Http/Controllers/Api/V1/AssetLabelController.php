<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Services\AssetLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AssetLabelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Asset $asset, AssetLabelService $service) : Response
    {
        Gate::authorize('view', $asset);

        $validated = $request->validate([
            'format' => [
                'sometimes',
                'string',
                Rule::in(['html', 'png', 'pdf']),
            ],
            'width_mm' => ['sometimes', 'numeric', 'min:50', 'max:150'],
            'height_mm' => ['sometimes', 'numeric', 'min:25', 'max:100'],
        ]);

        $format = $validated['format'] ?? 'html';
        $widthMm = (float) ($validated['width_mm'] ?? 90);
        $heightMm = (float) ($validated['height_mm'] ?? 50);

        if ($format !== 'html') {
            Gate::authorize('printLabel', $asset);
        }

        $headers = [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($format === 'png') {
            return response(
                $service->generatePng($asset, $widthMm, $heightMm),
                200,
                array_merge($headers, [
                    'Content-Type' => 'image/png',
                    'Content-Disposition' =>
                        'attachment; filename="asset-label-'
                        . $asset->asset_uid . '.png"',
                ])
            );
        }

        if ($format === 'pdf') {
            return response(
                $service->generatePdf($asset, $widthMm, $heightMm),
                200,
                array_merge($headers, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' =>
                        'attachment; filename="asset-label-'
                        . $asset->asset_uid . '.pdf"',
                ])
            );
        }

        return response(
            $service->renderHtml($asset, $widthMm, $heightMm),
            200,
            array_merge($headers, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Disposition' => 'inline',
            ])
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Asset $asset)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Asset $asset)
    {
        //
    }
}
