<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class ExampleBackgroundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $taskId,
        public readonly int $totalSteps = 10,
    ) {}

    public function handle(): void
    {
        $ttl = now()->addMinutes(10);

        Cache::put($this->cacheKey(), [
            'status' => 'processing',
            'progress' => 0,
            'message' => 'Memulai proses...',
        ], $ttl);

        for ($step = 1; $step <= $this->totalSteps; $step++) {
            sleep(1);

            Cache::put($this->cacheKey(), [
                'status' => 'processing',
                'progress' => (int) round(($step / $this->totalSteps) * 100),
                'message' => "Memproses data ke-{$step} dari {$this->totalSteps}...",
            ], $ttl);
        }

        Cache::put($this->cacheKey(), [
            'status' => 'completed',
            'progress' => 100,
            'message' => 'Proses selesai dengan sukses!',
        ], $ttl);
    }

    private function cacheKey(): string
    {
        return "example_live_task_{$this->taskId}";
    }
}
