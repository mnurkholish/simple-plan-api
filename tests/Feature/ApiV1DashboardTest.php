<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it returns dashboard data and live stats for authenticated users', function (): void {
    $this->seed();

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'juniyasyos@gmail.com',
        'password' => 'password',
        'device_name' => 'dashboard-test',
    ])->json('token');

    $this->withToken($token)
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'variant',
                'summary' => [
                    'total_users',
                    'total_roles',
                    'total_units',
                    'total_permissions',
                ],
                'current_user' => [
                    'roles',
                    'units',
                    'permissions_count',
                ],
                'role_distribution',
                'recent_users',
                'owned_units',
                'today' => [
                    'date',
                    'timezone',
                ],
            ],
        ])
        ->assertJsonPath('data.variant', 'super-admin');

    $this->withToken($token)
        ->getJson('/api/v1/dashboard/live-stats')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'online_users',
                'logins_today',
                'logouts_today',
            ],
        ]);
});
