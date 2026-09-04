<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function adminTokenForImpersonation(): string
{
    test()->seed();

    return test()->postJson('/api/v1/auth/login', [
        'email' => 'juniyasyos@gmail.com',
        'password' => 'password',
        'device_name' => 'feature-test',
    ])->json('token');
}

test('admin can start check and stop impersonating a user', function (): void {
    $adminToken = adminTokenForImpersonation();
    $target = User::where('email', 'perawat@gmail.com')->firstOrFail();

    $start = $this->withToken($adminToken)
        ->postJson("/api/v1/impersonation/{$target->id}/start");

    $impersonationToken = $start
        ->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.email', 'perawat@gmail.com')
        ->assertJsonPath('impersonator.email', 'juniyasyos@gmail.com')
        ->assertJsonPath('impersonation.active', true)
        ->json('token');

    $this->app['auth']->forgetGuards();

    $this->withToken($impersonationToken)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'perawat@gmail.com');

    $this->app['auth']->forgetGuards();

    $this->withToken($impersonationToken)
        ->getJson('/api/v1/impersonation/status')
        ->assertOk()
        ->assertJsonPath('data.active', true)
        ->assertJsonPath('data.impersonator.email', 'juniyasyos@gmail.com')
        ->assertJsonPath('data.impersonated.email', 'perawat@gmail.com');

    $this->app['auth']->forgetGuards();

    $this->withToken($impersonationToken)
        ->postJson('/api/v1/impersonation/stop')
        ->assertOk()
        ->assertJsonPath('user.email', 'juniyasyos@gmail.com')
        ->assertJsonPath('impersonation.active', false);

    $this->app['auth']->forgetGuards();

    $this->withToken($impersonationToken)
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();

    $this->app['auth']->forgetGuards();

    $this->withToken($adminToken)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'juniyasyos@gmail.com');
});

test('admin cannot impersonate themselves', function (): void {
    $adminToken = adminTokenForImpersonation();
    $admin = User::where('email', 'juniyasyos@gmail.com')->firstOrFail();

    $this->withToken($adminToken)
        ->postJson("/api/v1/impersonation/{$admin->id}/start")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('user');
});

test('user without impersonate permission cannot start impersonation', function (): void {
    test()->seed();
    $perawatToken = $this->postJson('/api/v1/auth/login', [
        'email' => 'perawat@gmail.com',
        'password' => 'password',
        'device_name' => 'feature-test',
    ])->json('token');
    $target = User::where('email', 'kepala@gmail.com')->firstOrFail();

    $this->withToken($perawatToken)
        ->postJson("/api/v1/impersonation/{$target->id}/start")
        ->assertForbidden();
});

test('admin cannot impersonate a user with impersonate permission', function (): void {
    $adminToken = adminTokenForImpersonation();
    $target = User::factory()->create([
        'email' => 'support-admin@example.test',
        'status' => 'active',
    ]);
    $target->givePermissionTo('impersonate');

    $this->withToken($adminToken)
        ->postJson("/api/v1/impersonation/{$target->id}/start")
        ->assertForbidden();
});
