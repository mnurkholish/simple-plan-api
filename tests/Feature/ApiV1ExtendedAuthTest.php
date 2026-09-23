<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

test('it registers user and returns bearer token', function (): void {
    Notification::fake();

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'User Baru',
        'email' => 'baru@example.com',
        'nip' => '998877',
        'password' => 'password',
        'password_confirmation' => 'password',
        'device_name' => 'feature-test',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.nama', 'User Baru')
        ->assertJsonStructure(['message', 'token', 'user']);

    $user = User::where('email', 'baru@example.com')->firstOrFail();
    expect(Hash::check('password', $user->password))->toBeTrue();

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('it sends password reset link notification', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email' => 'reset@example.com']);

    $response = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'reset@example.com',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', Password::RESET_LINK_SENT);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('it resets password and revokes existing tokens', function (): void {
    $user = User::factory()->create(['email' => 'reset@example.com']);
    $user->createToken('old-token');
    $token = Password::broker()->createToken($user);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'email' => 'reset@example.com',
        'token' => $token,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', Password::PASSWORD_RESET);

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0);
});

test('it sends email verification notification for authenticated user', function (): void {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $token = $user->createToken('feature-test')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/v1/auth/email/verification-notification');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'verification-link-sent');

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('it verifies email with signed api URL', function (): void {
    $user = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute(
        'api.v1.auth.email.verify',
        now()->addMinutes(60),
        [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ],
    );

    $response = $this->getJson($url);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'verified');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('it confirms authenticated user password', function (): void {
    $user = User::factory()->create(['password' => Hash::make('password')]);
    $token = $user->createToken('feature-test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/auth/confirm-password', ['password' => 'wrong'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    $this->withToken($token)
        ->postJson('/api/v1/auth/confirm-password', ['password' => 'password'])
        ->assertOk()
        ->assertJsonPath('message', 'Password berhasil dikonfirmasi.');
});
