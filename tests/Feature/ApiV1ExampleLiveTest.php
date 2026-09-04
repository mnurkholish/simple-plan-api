<?php

use App\Jobs\ExampleBackgroundJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function adminTokenForExampleLive(): string
{
    test()->seed();

    return test()->postJson('/api/v1/auth/login', [
        'email' => 'juniyasyos@gmail.com',
        'password' => 'password',
        'device_name' => 'feature-test',
    ])->json('token');
}

test('admin can read example live metadata', function (): void {
    $token = adminTokenForExampleLive();

    $this->withToken($token)
        ->getJson('/api/v1/example-live')
        ->assertOk()
        ->assertJsonPath('data.job.default_steps', 10)
        ->assertJsonStructure([
            'data' => [
                'ranges' => [
                    '*' => ['id', 'label'],
                ],
                'job',
            ],
        ]);
});

test('admin can start example background job', function (): void {
    Queue::fake();

    $token = adminTokenForExampleLive();

    $response = $this->withToken($token)
        ->postJson('/api/v1/example-live/start', ['steps' => 3]);

    $taskId = $response
        ->assertAccepted()
        ->assertJsonStructure(['taskId', 'message'])
        ->json('taskId');

    expect(Cache::get("example_live_task_{$taskId}"))
        ->toMatchArray([
            'status' => 'pending',
            'progress' => 0,
        ]);

    Queue::assertPushed(
        ExampleBackgroundJob::class,
        fn (ExampleBackgroundJob $job): bool => $job->taskId === $taskId && $job->totalSteps === 3,
    );
});

test('admin can check example task status', function (): void {
    $token = adminTokenForExampleLive();
    Cache::put('example_live_task_demo-task', [
        'status' => 'processing',
        'progress' => 40,
        'message' => 'Memproses data ke-2 dari 5...',
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/example-live/status/demo-task')
        ->assertOk()
        ->assertJsonPath('status', 'processing')
        ->assertJsonPath('progress', 40);
});

test('example task status returns not found when cache is missing', function (): void {
    $token = adminTokenForExampleLive();

    $this->withToken($token)
        ->getJson('/api/v1/example-live/status/missing-task')
        ->assertNotFound()
        ->assertJsonPath('status', 'error');
});

test('admin can read live market proxy data', function (): void {
    Http::fake([
        'query1.finance.yahoo.com/*' => Http::response([
            'chart' => [
                'result' => [
                    [
                        'meta' => [
                            'regularMarketPrice' => 16000,
                        ],
                        'timestamp' => [1700000000],
                        'indicators' => [
                            'quote' => [
                                ['close' => [16000]],
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $token = adminTokenForExampleLive();

    $this->withToken($token)
        ->getJson('/api/v1/example-live/real-market?range=live')
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('mode', 'live')
        ->assertJsonPath('source', 'yahoo')
        ->assertJsonPath('usd_idr', 16000);
});
