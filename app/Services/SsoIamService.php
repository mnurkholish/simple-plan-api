<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class SsoIamService
{
    /**
     * @return array{enabled: bool, login_url: ?string, logout_url: ?string, local_login_allowed: bool}
     */
    public function config(): array
    {
        $enabled = $this->enabled();

        return [
            'enabled' => $enabled,
            'login_url' => $enabled ? route('api.v1.auth.sso.login') : null,
            'logout_url' => $enabled ? $this->logoutUrl() : null,
            'local_login_allowed' => ! $enabled,
        ];
    }

    public function enabled(): bool
    {
        return (bool) config('iam.enabled', false);
    }

    public function loginRedirectUrl(): string
    {
        $iamBase = rtrim((string) config('iam.base_url'), '/');
        $callback = urlencode(route('api.v1.auth.sso.callback'));
        $appKey = urlencode((string) config('iam.app_key'));

        return "{$iamBase}/sso/redirect?app={$appKey}&callback={$callback}";
    }

    public function logoutUrl(): ?string
    {
        $configured = config('iam.logout_url');

        if ($configured) {
            return (string) $configured;
        }

        $iamBase = trim((string) config('iam.base_url'));

        return $iamBase !== '' ? rtrim($iamBase, '/').'/logout' : null;
    }

    /**
     * @return array{code: string, user: User, payload: array<string, mixed>}
     */
    public function createExchangeCode(string $iamToken): array
    {
        $payload = $this->validateToken($iamToken);
        $user = $this->provisionUser($payload);
        $code = Str::random(64);

        Cache::put($this->cacheKey($code), [
            'user_id' => $user->id,
            'payload' => $payload,
        ], now()->addSeconds((int) config('iam.exchange_ttl_seconds', 120)));

        return [
            'code' => $code,
            'user' => $user,
            'payload' => $payload,
        ];
    }

    public function exchangeCode(string $code): User
    {
        $key = $this->cacheKey($code);
        $data = Cache::pull($key);

        if (! is_array($data) || empty($data['user_id'])) {
            throw ValidationException::withMessages([
                'code' => ['Kode SSO tidak valid atau sudah kedaluwarsa.'],
            ]);
        }

        return User::findOrFail($data['user_id']);
    }

    /**
     * @return array<string, mixed>
     */
    public function validateToken(string $token): array
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            throw ValidationException::withMessages([
                'token' => ['Format token SSO tidak valid.'],
            ]);
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $segments;
        $header = $this->decodeJsonSegment($encodedHeader, 'header');
        $payload = $this->decodeJsonSegment($encodedPayload, 'payload');
        $algorithm = (string) config('iam.jwt_algorithm', 'HS256');

        if (($header['alg'] ?? null) !== $algorithm || $algorithm !== 'HS256') {
            throw ValidationException::withMessages([
                'token' => ['Algoritma token SSO tidak didukung.'],
            ]);
        }

        $expected = hash_hmac(
            'sha256',
            "{$encodedHeader}.{$encodedPayload}",
            (string) config('iam.jwt_secret'),
            true,
        );
        $actual = $this->base64UrlDecode($encodedSignature);

        if (! hash_equals($expected, $actual)) {
            throw ValidationException::withMessages([
                'token' => ['Signature token SSO tidak valid.'],
            ]);
        }

        $this->validateClaims($payload);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function provisionUser(array $payload): User
    {
        $mapping = config('iam.user_fields', []);
        $identifierField = (string) config('iam.identifier_field', 'email');
        $identifierClaim = $mapping[$identifierField] ?? $identifierField;
        $identifierValue = Arr::get($payload, $identifierClaim);

        if (! $identifierValue) {
            throw ValidationException::withMessages([
                'token' => ["Claim {$identifierClaim} wajib ada pada token SSO."],
            ]);
        }

        $attributes = collect($mapping)
            ->mapWithKeys(fn (string $claim, string $field): array => [
                $field => Arr::get($payload, $claim),
            ])
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->all();

        $attributes['status'] = $attributes['status'] ?? 'active';
        $attributes['email_verified_at'] = now();

        if (empty($attributes['password'])) {
            $attributes['password'] = Str::password(32);
        }

        $user = User::updateOrCreate(
            [$identifierField => (string) $identifierValue],
            $attributes,
        );

        if ((bool) config('iam.sync_roles', true)) {
            $roles = $this->rolesFromPayload($payload);

            collect($roles)->each(fn (string $role): Role => Role::firstOrCreate([
                'name' => $role,
                'guard_name' => config('iam.role_guard_name', 'web'),
            ]));

            $user->syncRoles($roles);
        }

        if ((bool) config('iam.sync_unit_kerja', true)) {
            $unitName = collect(Arr::get($payload, config('iam.unit_kerja_field', 'unit_kerja'), []))
                ->map(fn (mixed $unit): ?string => is_array($unit)
                    ? ($unit['unit_name'] ?? $unit['name'] ?? $unit['slug'] ?? null)
                    : (is_string($unit) ? $unit : null))
                ->filter()
                ->first();

            if ($unitName) {
                $unitId = Unit::firstOrCreate([
                    'unit_name' => $unitName,
                ], [
                    'slug' => Str::slug($unitName),
                ])->id;

                $user->update(['unit_id' => $unitId]);
            }
        }

        return $user->load(['roles.permissions', 'unit']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validateClaims(array $payload): void
    {
        $now = now()->timestamp;
        $leeway = (int) config('iam.jwt_leeway', 0);

        if (($payload['exp'] ?? null) && ((int) $payload['exp'] + $leeway) < $now) {
            throw ValidationException::withMessages([
                'token' => ['Token SSO sudah kedaluwarsa.'],
            ]);
        }

        if (($payload['nbf'] ?? null) && ((int) $payload['nbf'] - $leeway) > $now) {
            throw ValidationException::withMessages([
                'token' => ['Token SSO belum berlaku.'],
            ]);
        }

        $appKey = (string) config('iam.app_key');
        $tokenApp = $payload['app_key'] ?? $payload['app'] ?? null;

        if ($appKey !== '' && $tokenApp !== null && $tokenApp !== $appKey) {
            throw ValidationException::withMessages([
                'token' => ['Token SSO bukan untuk aplikasi ini.'],
            ]);
        }

        $issuer = config('iam.issuer');

        if ($issuer && ($payload['iss'] ?? null) !== $issuer) {
            throw ValidationException::withMessages([
                'token' => ['Issuer token SSO tidak valid.'],
            ]);
        }

        $audience = config('iam.audience');

        if ($audience) {
            $audiences = Arr::wrap($payload['aud'] ?? []);

            if (! in_array($audience, $audiences, true)) {
                throw ValidationException::withMessages([
                    'token' => ['Audience token SSO tidak valid.'],
                ]);
            }
        }

        $roles = $this->rolesFromPayload($payload);
        $requiredRoles = config('iam.required_roles', []);

        if ((bool) config('iam.require_roles', false) && ! (bool) config('iam.allow_roleless_sso', true) && empty($roles)) {
            throw ValidationException::withMessages([
                'token' => ['Token SSO tidak memiliki role.'],
            ]);
        }

        if (! empty($requiredRoles) && empty(array_intersect($requiredRoles, $roles))) {
            throw ValidationException::withMessages([
                'token' => ['Token SSO tidak memiliki role yang diperlukan.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function rolesFromPayload(array $payload): array
    {
        return collect(Arr::get($payload, config('iam.roles_field', 'roles'), []))
            ->map(fn (mixed $role): ?string => is_array($role)
                ? ($role['slug'] ?? $role['name'] ?? null)
                : (is_string($role) ? $role : null))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonSegment(string $segment, string $name): array
    {
        $decoded = json_decode($this->base64UrlDecode($segment), true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'token' => ["Segment {$name} token SSO tidak valid."],
            ]);
        }

        return $decoded;
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;

        if ($padding) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(strtr($value, '-_', '+/'), true) ?: '';
    }

    private function cacheKey(string $code): string
    {
        return 'sso_exchange_code_'.$code;
    }
}
