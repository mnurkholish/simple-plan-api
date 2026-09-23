<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it logs in with sanctum bearer token response', function (): void {
    $this->seed();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'juniyasyos@gmail.com',
        'password' => 'password',
        'device_name' => 'feature-test',
    ]);

    $response
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'token_type',
            'token',
            'user' => [
                'id',
                'nama',
                'unit_user',
                'jabatan',
                'role',
                'status_user',
            ],
        ])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.nama', 'Ahmad Ilyas');
});

test('it returns paginated users json for authenticated admin', function (): void {
    $this->seed();

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'juniyasyos@gmail.com',
        'password' => 'password',
        'device_name' => 'feature-test',
    ])->json('token');

    $response = $this->withToken($token)
        ->getJson('/api/v1/users');

    $response
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => [
                        'id',
                        'nama',
                        'unit_user',
                        'jabatan',
                        'role',
                        'status_user',
                    ],
                ],
                'links',
                'meta',
            ],
        ]);
});
