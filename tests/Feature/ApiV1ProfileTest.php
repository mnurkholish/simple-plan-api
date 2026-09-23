<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('authenticated user can update profile avatar', function (): void {
    Storage::fake('public');
    $this->seed();

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'juniyasyos@gmail.com',
        'password' => 'password',
        'device_name' => 'feature-test',
    ])->json('token');

    $response = $this->withToken($token)
        ->post('/api/v1/profile', [
            '_method' => 'PATCH',
            'name' => 'Juniyas Yos',
            'email' => 'juniyasyos@gmail.com',
            'nip' => '1234567890',
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 128, 128),
        ], [
            'Accept' => 'application/json',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.nama', 'Juniyas Yos')
        ->assertJsonStructure([
            'data' => [
                'id',
                'nama',
                'unit_user',
                'jabatan',
                'role',
                'status_user',
            ],
            'message',
        ]);

    $user = User::query()->where('email', 'juniyasyos@gmail.com')->firstOrFail();
    $avatarPath = $user->getRawOriginal('avatar');

    expect($user->name)->toBe('Juniyas Yos')
        ->and($avatarPath)->toStartWith('avatars/');
    Storage::disk('public')->assertExists($avatarPath);
});
