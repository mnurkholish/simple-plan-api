<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\DashboardService;
use App\Services\SsoIamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SsoController extends Controller
{
    public function __construct(
        private readonly SsoIamService $sso,
        private readonly DashboardService $dashboardService,
    ) {}

    public function config(): JsonResponse
    {
        return response()->json([
            'data' => $this->sso->config(),
        ]);
    }

    public function login(): JsonResponse|RedirectResponse
    {
        if (! $this->sso->enabled()) {
            return response()->json([
                'message' => 'SSO sedang tidak aktif.',
            ], 409);
        }

        return redirect()->away($this->sso->loginRedirectUrl());
    }

    public function callback(Request $request): JsonResponse|RedirectResponse
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        if (! $this->sso->enabled()) {
            return redirect()->away($frontendUrl.'/login?sso_error=sso-disabled');
        }

        $iamToken = $request->input('token') ?? $request->input('access_token');

        if (! $iamToken) {
            $error = $request->input('error_description')
                ?? $request->input('error')
                ?? 'missing-token';

            return redirect()->away($frontendUrl.'/login?sso_error='.urlencode((string) $error));
        }

        $result = $this->sso->createExchangeCode((string) $iamToken);

        return redirect()->away($frontendUrl.'/sso/callback?code='.$result['code']);
    }

    public function exchange(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $this->sso->exchangeCode($data['code']);
        $token = $user->createToken($data['device_name'] ?? 'sso-token')->plainTextToken;
        $this->dashboardService->recordLogin();

        return response()->json([
            'message' => 'Login SSO berhasil.',
            'token_type' => 'Bearer',
            'token' => $token,
            'user' => new UserResource($user->load(['roles.permissions', 'unit'])),
        ]);
    }
}
