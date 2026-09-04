<?php

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
        ->assertJsonPath('data.email', 'juniyasyos@gmail.com')
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
                'nip',
                'avatar',
                'roles',
                'units',
                'permissions',
            ],
            'message',
        ]);

    $avatarUrl = $response->json('data.avatar');
    expect($avatarUrl)->toContain('/storage/avatars/');

    $avatarPath = str($avatarUrl)->after('/storage/')->toString();
    Storage::disk('public')->assertExists($avatarPath);
});
