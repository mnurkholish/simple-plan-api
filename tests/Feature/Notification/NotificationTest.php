<?php

use App\Models\User;
use App\Notifications\SystemActivityNotification;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('aktivitas login dan logout tersimpan untuk pengguna terkait', function (): void {
    $user = User::factory()->create();
    $service = app(NotificationService::class);

    $service->recordLogin($user);
    $service->recordLogout($user);

    expect($user->notifications()->get()->pluck('data')->pluck('title')->all())
        ->toContain('Login berhasil', 'Logout berhasil');
});

test('daftar notifikasi menampilkan milik pengguna dengan read status dan pagination', function (): void {
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
        ->assertJsonPath('data.0.icon', 'IconInfoCircle')
        ->assertJsonPath('data.0.color', 'slate')
        ->assertJsonMissingPath('data.0.ticket_id')
        ->assertJsonMissingPath('data.0.maintenance_id')
        ->assertJsonMissingPath('data.0.time')
        ->assertJsonMissingPath('data.0.read_at')
        ->assertJsonMissingPath('activity.0.icon');

    $this->getJson('/api/v1/notifications?per_page=1&page=2')
        ->assertOk()
        ->assertJsonPath('data.0.id', $read->id)
        ->assertJsonPath('data.0.is_read', true);
});

test('daftar notifikasi menolak parameter paginasi yang tidak valid', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/notifications?page=0&per_page=101')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['page', 'per_page']);
});

test('notifikasi hanya mengizinkan pengguna menandai miliknya sebagai dibaca', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $user->notify(new SystemActivityNotification('Milik saya', 'Baru'));
    $other->notify(new SystemActivityNotification('Milik lain', 'Baru'));
    $ownId = $user->notifications()->firstOrFail()->id;
    $otherId = $other->notifications()->firstOrFail()->id;
    Sanctum::actingAs($user);

    $this->patchJson("/api/v1/notifications/{$otherId}/read")->assertNotFound();
    $this->patchJson("/api/v1/notifications/{$ownId}/read")
        ->assertOk()
        ->assertJsonPath('unread_count', 0)
        ->assertJsonPath('data.0.is_read', true);
    expect($other->notifications()->firstOrFail()->read_at)->toBeNull();
});

test('notifikasi menandai semua milik pengguna sebagai dibaca', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $user->notify(new SystemActivityNotification('Milik saya', 'Baru'));
    $other->notify(new SystemActivityNotification('Milik lain', 'Baru'));
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('unread_count', 0);
    expect($user->notifications()->firstOrFail()->read_at)->not->toBeNull()
        ->and($other->notifications()->firstOrFail()->read_at)->toBeNull();
});
