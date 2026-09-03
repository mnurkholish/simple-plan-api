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
                'name',
                'email',
                'nip',
                'status',
                'roles',
                'units',
                'permissions',
            ],
        ])
        ->assertJsonPath('token_type', 'Bearer');
});

test('it returns paginated users json for authenticated admin', function (): void {
    $this->seed();

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'juniyasyos@gmail.com',
        'password' => 'password',
        'device_name' => 'feature-test',
    ])->json('token');

    $response = $this->withToken($token)
        ->getJson('/api/v1/users?include=roles,units');

    $response
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'email',
                    'nip',
                    'avatar',
                    'status',
                    'roles',
                    'units',
                    'created_at',
                ],
            ],
            'links',
            'meta',
        ]);
});
