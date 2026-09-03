<?php

namespace App\Traits;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

trait HasConsistentResponse
{
    public function handleJsonAction(Closure $action, string $successMessage, int $status = 200): JsonResponse
    {
        try {
            $data = $action();

            return response()->json([
                'message' => $successMessage,
                'data' => $data,
            ], $status);
        } catch (Throwable $exception) {
            Log::error('Action failed: '.$exception->getMessage(), [
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Terjadi kesalahan.',
            ], 500);
        }
    }
}
