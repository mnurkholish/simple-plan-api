<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        [$field, $identifier] = $this->credentials($request);

        $user = User::query()
            ->where($field, $identifier)
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Kredensial yang diberikan tidak valid.'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'login' => ['Akun tidak aktif.'],
            ]);
        }

        $token = $user->createToken($data['device_name'] ?? 'api-token')->plainTextToken;
        $this->dashboardService->recordLogin();
        $this->notificationService->recordLogin($user);

        $user->load(['roles.permissions', 'unit']);

        return response()->json([
            'message' => 'Login berhasil.',
            'token_type' => 'Bearer',
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $this->notificationService->recordLogout($user);
        }

        $user?->currentAccessToken()?->delete();
        $this->dashboardService->recordLogout();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource(
            $request->user()->load(['roles.permissions', 'unit'])
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function credentials(LoginRequest $request): array
    {
        if ($request->filled('email')) {
            return ['email', $request->string('email')->toString()];
        }

        if ($request->filled('nip')) {
            return ['nip', $request->string('nip')->toString()];
        }

        $login = $request->string('login')->toString();

        return filter_var($login, FILTER_VALIDATE_EMAIL)
            ? ['email', $login]
            : ['nip', $login];
    }
}
