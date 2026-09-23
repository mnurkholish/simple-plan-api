<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

function ssoJwt(array $payload, string $secret = 'secret'): string
{
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $segments = [
        ssoBase64Url(json_encode($header)),
        ssoBase64Url(json_encode($payload)),
    ];
    $signature = hash_hmac('sha256', implode('.', $segments), $secret, true);
    $segments[] = ssoBase64Url($signature);

    return implode('.', $segments);
}

function ssoBase64Url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

test('it returns sso config while disabled by default', function (): void {
    config()->set('iam.enabled', false);

    $response = $this->getJson('/api/v1/auth/sso/config');

    $response
        ->assertOk()
        ->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.login_url', null)
        ->assertJsonPath('data.local_login_allowed', true);
});

test('it redirects to iam login when sso is enabled', function (): void {
    config()->set('iam.enabled', true);
    config()->set('iam.app_key', 'simple-plan');
    config()->set('iam.base_url', 'https://iam.example.test');

    $response = $this->get('/api/v1/auth/sso/login');

    $response
        ->assertRedirect()
        ->assertRedirectContains('https://iam.example.test/sso/redirect?app=simple-plan&callback=');
});

test('it exchanges a valid sso token for sanctum bearer token', function (): void {
    Cache::flush();
    config()->set('iam.enabled', true);
    config()->set('iam.app_key', 'simple-plan');
    config()->set('iam.jwt_secret', 'secret');
    config()->set('iam.issuer', null);
    config()->set('iam.audience', null);

    $token = ssoJwt([
        'type' => 'access',
        'app_key' => 'simple-plan',
        'sub' => 'iam-123',
        'name' => 'SSO User',
        'email' => 'sso@example.com',
        'nip' => 'SSO-001',
        'roles' => [['slug' => 'sso-admin']],
        'unit_kerja' => ['ICU'],
        'exp' => now()->addMinutes(5)->timestamp,
    ]);

    $callback = $this->get('/api/v1/auth/sso/callback?token='.$token);
    $callback->assertRedirectContains('/sso/callback?code=');

    parse_str(parse_url($callback->headers->get('Location'), PHP_URL_QUERY), $query);

    $response = $this->postJson('/api/v1/auth/sso/exchange', [
        'code' => $query['code'],
        'device_name' => 'feature-test',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Login SSO berhasil.')
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.nama', 'SSO User')
        ->assertJsonPath('user.unit_user', 'ICU')
        ->assertJsonPath('user.role', 'sso-admin')
        ->assertJsonStructure(['token', 'user' => ['id', 'nama', 'unit_user', 'jabatan', 'role', 'status_user']]);

    $user = User::where('email', 'sso@example.com')->firstOrFail();
    expect($user->hasRole('sso-admin'))->toBeTrue()
        ->and($user->unit()->where('unit_name', 'ICU')->exists())->toBeTrue();
});

test('it rejects reused sso exchange code', function (): void {
    Cache::put('sso_exchange_code_once', ['user_id' => User::factory()->create()->id], now()->addMinute());

    $this->postJson('/api/v1/auth/sso/exchange', ['code' => 'once'])
        ->assertOk();

    $this->postJson('/api/v1/auth/sso/exchange', ['code' => 'once'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');
});

test('it rejects invalid sso token signature', function (): void {
    config()->set('iam.enabled', true);
    config()->set('iam.jwt_secret', 'secret');
    config()->set('iam.issuer', null);
    config()->set('iam.audience', null);

    $token = ssoJwt([
        'app_key' => 'client-app',
        'email' => 'sso@example.com',
        'name' => 'SSO User',
        'exp' => now()->addMinutes(5)->timestamp,
    ], 'wrong-secret');

    $this->get('/api/v1/auth/sso/callback?token='.$token)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('token');
});
