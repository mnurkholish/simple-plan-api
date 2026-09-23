<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserDetailResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    public function index(): JsonResponse
    {
        $users = $this->userService->getAllUsers();

        $message = $users->isEmpty() ? 'User tidak ditemukan' : 'Berhasil mengambil daftar user.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => UserResource::collection($users)->response()->getData(true),
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $userWithRel = $this->userService->getUserById($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil detail user.',
            'data' => new UserDetailResource($userWithRel),
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->createUser($request->validated());
        $user->assignRole($request->role);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil menambahkan user baru.',
            'data' => new UserDetailResource($user->load(['unit', 'roles'])),
        ], 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $updatedUser = $this->userService->updateUser($user, $request->validated());

        if ($request->filled('role')) {
            $updatedUser->syncRoles($request->role);
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengubah data user.',
            'data' => new UserDetailResource($updatedUser->load(['unit', 'roles'])),
        ]);
    }

    public function toggleStatus(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'status_user' => ['required', Rule::in(['Aktif', 'Nonaktif'])],
            'alasan_nonaktif' => ['required_if:status_user,Nonaktif', Rule::in(['Resign', 'Cuti Panjang', 'Mutasi', 'Lainnya'])],
        ], [
            'required' => 'Form wajib diisi',
            'alasan_nonaktif.required_if' => 'Format data tidak sesuai. Silahkan periksa kembali data yang dimasukkan',
            '*' => 'Format data tidak sesuai. Silahkan periksa kembali data yang dimasukkan',
        ]);

        $updatedUser = $this->userService->toggleStatus($user, $request->only(['status_user', 'alasan_nonaktif']));

        $message = $request->status_user === 'Nonaktif'
            ? 'User berhasil dinonaktifkan'
            : 'User berhasil diaktifkan kembali';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => new UserDetailResource($updatedUser->load(['unit', 'roles'])),
        ]);
    }
}
