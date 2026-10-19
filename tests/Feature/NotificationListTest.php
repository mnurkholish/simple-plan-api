<?php

use App\Models\User;
use App\Notifications\SystemActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('notifications include read and unread items with pagination scoped to the user', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $user->notify(new SystemActivityNotification('Dibaca', 'Selesai'));
    $read = $user->notifications()->firstOrFail();
    $read->markAsRead();
    $read->forceFill(['created_at' => now()->subMinute()])->save();
    $user->notify(new SystemActivityNotification('Baru', 'Tiket baru'));
    $other->notify(new SystemActivityNotification('Milik orang lain', 'Rahasia'));

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/notifications?per_page=1&page=1')
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('current_page', 1)
        ->assertJsonPath('last_page', 2)
        ->assertJsonPath('per_page', 1)
        ->assertJsonPath('data.0.is_read', false)
        ->assertJsonMissingPath('data.0.icon')
        ->assertJsonMissingPath('data.0.color')
        ->assertJsonMissingPath('data.0.time')
        ->assertJsonMissingPath('data.0.read_at')
        ->assertJsonMissingPath('activity.0.icon');

    $this->getJson('/api/v1/notifications?per_page=1&page=2')
        ->assertOk()
        ->assertJsonPath('data.0.id', $read->id)
        ->assertJsonPath('data.0.is_read', true);
});

test('notification pagination validates query parameters', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/notifications?page=0&per_page=101')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['page', 'per_page']);
});
