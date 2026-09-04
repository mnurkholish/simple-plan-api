<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function adminTokenForNotifications(): string
{
    test()->seed();

    return test()->postJson('/api/v1/auth/login', [
        'email' => 'juniyasyos@gmail.com',
        'password' => 'password',
        'device_name' => 'notification-test',
    ])->json('token');
}

test('authenticated user can list notifications and activity summary', function (): void {
    $token = adminTokenForNotifications();

    $this->withToken($token)
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'title', 'subtitle', 'icon', 'color', 'time'],
            ],
            'activity' => [
                '*' => ['id', 'title', 'subtitle', 'icon', 'color', 'time'],
            ],
            'unread_count',
        ]);
});

test('authenticated user can mark one notification as read', function (): void {
    $token = adminTokenForNotifications();
    $notificationId = $this->withToken($token)
        ->getJson('/api/v1/notifications')
        ->json('data.0.id');

    $this->withToken($token)
        ->patchJson("/api/v1/notifications/{$notificationId}/read")
        ->assertOk()
        ->assertJsonPath('unread_count', 0);
});

test('authenticated user can mark all notifications as read', function (): void {
    $token = adminTokenForNotifications();

    $this->withToken($token)
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'juniyasyos@gmail.com',
        'password' => 'password',
        'device_name' => 'notification-test-2',
    ])->json('token');

    $this->withToken($token)
        ->patchJson('/api/v1/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('unread_count', 0);
});
