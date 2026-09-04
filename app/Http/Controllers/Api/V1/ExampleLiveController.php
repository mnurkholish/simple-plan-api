<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ExampleBackgroundJob;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ExampleLiveController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'ranges' => [
                    ['id' => 'live', 'label' => 'Live'],
                    ['id' => '1d', 'label' => '1 Hari'],
                    ['id' => '7d', 'label' => '7 Hari'],
                    ['id' => '30d', 'label' => '30 Hari'],
                ],
                'job' => [
                    'min_steps' => 1,
                    'max_steps' => 60,
                    'default_steps' => 10,
                ],
            ],
        ]);
    }

    public function realMarketData(Request $request): JsonResponse
    {
        $range = $request->validate([
            'range' => ['nullable', 'in:live,1d,7d,30d'],
        ])['range'] ?? 'live';

        if ($range === 'live') {
            return response()->json($this->liveMarketData());
        }

        return response()->json($this->historicalMarketData($range));
    }

    public function startJob(Request $request): JsonResponse
    {
        $data = $request->validate([
            'steps' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $taskId = Str::uuid()->toString();
        $totalSteps = (int) ($data['steps'] ?? 10);

        Cache::put($this->taskKey($taskId), [
            'status' => 'pending',
            'progress' => 0,
            'message' => 'Menyiapkan tugas latar belakang...',
        ], now()->addMinutes(10));

        ExampleBackgroundJob::dispatch($taskId, $totalSteps);

        return response()->json([
            'taskId' => $taskId,
            'message' => 'Tugas berhasil dimulai di latar belakang.',
        ], 202);
    }

    public function checkStatus(string $taskId): JsonResponse
    {
        $status = Cache::get($this->taskKey($taskId));

        if (! $status) {
            return response()->json([
                'status' => 'error',
                'progress' => 0,
                'message' => 'Tugas tidak ditemukan atau telah kadaluarsa.',
            ], 404);
        }

        return response()->json($status);
    }

    /**
     * @return array<string, mixed>
     */
    private function liveMarketData(): array
    {
        try {
            $usdResponse = Http::timeout(3)->get('https://query1.finance.yahoo.com/v8/finance/chart/USDIDR=X');
            $ihsgResponse = Http::timeout(3)->get('https://query1.finance.yahoo.com/v8/finance/chart/^JKSE');

            if ($usdResponse->successful() && $ihsgResponse->successful()) {
                return [
                    'status' => 'success',
                    'mode' => 'live',
                    'source' => 'yahoo',
                    'usd_idr' => $this->marketPrice($usdResponse, 15500),
                    'ihsg' => $this->marketPrice($ihsgResponse, 7500),
                ];
            }
        } catch (\Throwable) {
            // Local/dev environments may not have outbound network access.
        }

        return [
            'status' => 'success',
            'mode' => 'live',
            'source' => 'fallback',
            'usd_idr' => 15500,
            'ihsg' => 7500,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function historicalMarketData(string $range): array
    {
        [$yahooRange, $interval] = match ($range) {
            '7d' => ['5d', '30m'],
            '30d' => ['1mo', '1d'],
            default => ['1d', '5m'],
        };

        try {
            $usdResponse = Http::timeout(5)->get("https://query1.finance.yahoo.com/v8/finance/chart/USDIDR=X?range={$yahooRange}&interval={$interval}");
            $ihsgResponse = Http::timeout(5)->get("https://query1.finance.yahoo.com/v8/finance/chart/^JKSE?range={$yahooRange}&interval={$interval}");

            if ($usdResponse->successful() && $ihsgResponse->successful()) {
                $usdData = $this->extractChartData($usdResponse, $interval);
                $ihsgData = $this->extractChartData($ihsgResponse, $interval);

                return [
                    'status' => 'success',
                    'mode' => 'history',
                    'source' => 'yahoo',
                    'labels' => $usdData['labels'],
                    'usd_data' => $usdData['prices'],
                    'ihsg_data' => $ihsgData['prices'],
                    'current_usd' => $this->marketPrice($usdResponse, 15500),
                    'current_ihsg' => $this->marketPrice($ihsgResponse, 7500),
                ];
            }
        } catch (\Throwable) {
            // Local/dev environments may not have outbound network access.
        }

        return $this->fallbackHistoricalData($range);
    }

    private function marketPrice(Response $response, int|float $fallback): int|float
    {
        return $response->json('chart.result.0.meta.regularMarketPrice') ?: $fallback;
    }

    /**
     * @return array{labels: array<int, string>, prices: array<int, int|float|null>}
     */
    private function extractChartData(Response $response, string $interval): array
    {
        $result = $response->json('chart.result.0');

        if (! $result) {
            return ['labels' => [], 'prices' => []];
        }

        $timestamps = $result['timestamp'] ?? [];
        $prices = $result['indicators']['quote'][0]['close'] ?? [];

        return [
            'labels' => array_map(
                fn (int $timestamp): string => Carbon::createFromTimestamp($timestamp)
                    ->timezone('Asia/Jakarta')
                    ->format($interval === '1d' ? 'd M' : 'd M H:i'),
                $timestamps,
            ),
            'prices' => $prices,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackHistoricalData(string $range): array
    {
        $points = $range === '30d' ? 30 : 12;
        $labels = [];
        $usd = [];
        $ihsg = [];

        for ($index = $points - 1; $index >= 0; $index--) {
            $date = now()->subHours($range === '30d' ? $index * 24 : $index * 2);
            $labels[] = $date->format($range === '30d' ? 'd M' : 'd M H:i');
            $usd[] = 15500 + (($points - $index) * 7);
            $ihsg[] = 7500 + (($points - $index) * 3);
        }

        return [
            'status' => 'success',
            'mode' => 'history',
            'source' => 'fallback',
            'labels' => $labels,
            'usd_data' => $usd,
            'ihsg_data' => $ihsg,
            'current_usd' => end($usd),
            'current_ihsg' => end($ihsg),
        ];
    }

    private function taskKey(string $taskId): string
    {
        return "example_live_task_{$taskId}";
    }
}
