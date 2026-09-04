<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class ImpersonationService
{
    /**
     * @return array{
     *     token: string,
     *     user: User,
     *     impersonator: User,
     *     impersonation: array{active: bool, impersonator_id: int, impersonated_id: int, started_at: string}
     * }
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function start(User $impersonator, User $target, ?object $currentToken): array
    {
        if (! $currentToken || ! isset($currentToken->id)) {
            throw ValidationException::withMessages([
                'token' => ['Token autentikasi tidak valid.'],
            ]);
        }

        if ($this->getRecord($currentToken)) {
            throw ValidationException::withMessages([
                'impersonation' => ['Anda sudah berada dalam mode impersonate.'],
            ]);
        }

        if (! $impersonator->canImpersonate()) {
            throw new AuthorizationException('Anda tidak memiliki akses impersonate.');
        }

        if ($impersonator->is($target)) {
            throw ValidationException::withMessages([
                'user' => ['Tidak bisa impersonate akun sendiri.'],
            ]);
        }

        if (! $target->canBeImpersonated()) {
            throw new AuthorizationException('User ini tidak dapat di-impersonate.');
        }

        if ($target->status !== 'active') {
            throw ValidationException::withMessages([
                'user' => ['Hanya user aktif yang dapat di-impersonate.'],
            ]);
        }

        $plainTextToken = $target->createToken('impersonation-token', ['impersonation'])->plainTextToken;
        $impersonationTokenId = $this->plainTextTokenId($plainTextToken);
        $startedAt = now();

        Cache::put(
            $this->cacheKey($impersonationTokenId),
            [
                'impersonator_id' => $impersonator->id,
                'impersonated_id' => $target->id,
                'impersonator_token_id' => $currentToken->id,
                'started_at' => $startedAt->toJSON(),
            ],
            $startedAt->copy()->addMinutes($this->ttlMinutes()),
        );

        return [
            'token' => $plainTextToken,
            'user' => $target->load(['roles.permissions', 'units']),
            'impersonator' => $impersonator->load(['roles.permissions', 'units']),
            'impersonation' => [
                'active' => true,
                'impersonator_id' => $impersonator->id,
                'impersonated_id' => $target->id,
                'started_at' => $startedAt->toJSON(),
            ],
        ];
    }

    /**
     * @return array{active: bool, impersonator: User|null, impersonated: User|null, started_at: string|null}
     */
    public function status(?object $currentToken): array
    {
        $record = $this->getRecord($currentToken);

        if (! $record) {
            return [
                'active' => false,
                'impersonator' => null,
                'impersonated' => null,
                'started_at' => null,
            ];
        }

        $impersonator = User::with(['roles.permissions', 'units'])->find($record['impersonator_id']);
        $impersonated = User::with(['roles.permissions', 'units'])->find($record['impersonated_id']);

        if (! $impersonator || ! $impersonated) {
            $this->forget($currentToken);

            return [
                'active' => false,
                'impersonator' => null,
                'impersonated' => null,
                'started_at' => null,
            ];
        }

        return [
            'active' => true,
            'impersonator' => $impersonator,
            'impersonated' => $impersonated,
            'started_at' => $record['started_at'] ?? null,
        ];
    }

    /**
     * @return array{user: User, impersonation: array{active: bool}}
     *
     * @throws ValidationException
     */
    public function stop(?object $currentToken): array
    {
        $status = $this->status($currentToken);

        if (! $status['active'] || ! $status['impersonator']) {
            throw ValidationException::withMessages([
                'impersonation' => ['Tidak sedang berada dalam mode impersonate.'],
            ]);
        }

        $this->forget($currentToken);
        $currentToken?->delete();

        return [
            'user' => $status['impersonator'],
            'impersonation' => [
                'active' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getRecord(?object $token): ?array
    {
        if (! $token || ! isset($token->id)) {
            return null;
        }

        $record = Cache::get($this->cacheKey((int) $token->id));

        return is_array($record) ? $record : null;
    }

    private function forget(?object $token): void
    {
        if ($token && isset($token->id)) {
            Cache::forget($this->cacheKey((int) $token->id));
        }
    }

    private function cacheKey(int $tokenId): string
    {
        return "impersonation:{$tokenId}";
    }

    private function plainTextTokenId(string $plainTextToken): int
    {
        return (int) str($plainTextToken)->before('|')->toString();
    }

    private function ttlMinutes(): int
    {
        return max((int) (config('sanctum.expiration') ?: 480), 1);
    }
}
