<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ImpersonationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function __construct(private readonly ImpersonationService $service) {}

    public function start(Request $request, User $user): JsonResponse
    {
        $result = $this->service->start(
            $request->user(),
            $user,
            $request->user()?->currentAccessToken(),
        );

        return response()->json([
            'message' => "Berhasil masuk sebagai {$user->name}.",
            'token_type' => 'Bearer',
            'token' => $result['token'],
            'user' => new UserResource($result['user']),
            'impersonator' => new UserResource($result['impersonator']),
            'impersonation' => $result['impersonation'],
        ]);
    }

    public function stop(Request $request): JsonResponse
    {
        $result = $this->service->stop($request->user()?->currentAccessToken());

        return response()->json([
            'message' => 'Mode impersonate dihentikan.',
            'user' => new UserResource($result['user']),
            'impersonation' => $result['impersonation'],
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $status = $this->service->status($request->user()?->currentAccessToken());

        return response()->json([
            'data' => [
                'active' => $status['active'],
                'impersonator' => $status['impersonator']
                    ? new UserResource($status['impersonator'])
                    : null,
                'impersonated' => $status['impersonated']
                    ? new UserResource($status['impersonated'])
                    : null,
                'started_at' => $status['started_at'],
            ],
        ]);
    }
}
