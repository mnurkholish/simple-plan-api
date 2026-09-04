<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UnitResource;
use App\Http\Resources\UserResource;
use App\Models\Unit;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    public function __construct(private readonly UserService $service) {}

    public function index()
    {
        $baseQuery = $this->applySearch(
            User::query(),
            ['name', 'email', 'nip', 'status'],
        );

        $query = QueryBuilder::for($baseQuery)
            ->allowedFilters('name', 'email', 'nip', 'status')
            ->allowedSorts('name', 'email', 'nip', 'created_at')
            ->allowedIncludes('roles', 'units')
            ->with(['roles', 'units'])
            ->defaultSort('-created_at');

        return UserResource::collection($this->dynamicPaginate($query));
    }

    public function store(UserRequest $request): JsonResponse
    {
        $user = $this->service->store($request->validated(), $request->file('avatar'));

        return (new UserResource($user->load(['roles.permissions', 'units'])))
            ->additional(['message' => 'Pengguna berhasil ditambahkan.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load(['roles.permissions', 'units']));
    }

    public function update(UserRequest $request, User $user): UserResource
    {
        $user = $this->service->update($user, $request->validated(), $request->file('avatar'));

        return (new UserResource($user->load(['roles.permissions', 'units'])))
            ->additional(['message' => 'Pengguna berhasil diperbarui.']);
    }

    public function destroy(string $ids): JsonResponse
    {
        $this->service->destroy(explode(',', $ids));

        return response()->json([
            'message' => 'Pengguna berhasil dihapus.',
        ]);
    }

    public function options(): JsonResponse
    {
        return response()->json([
            'roles' => Role::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'units' => UnitResource::collection(
                Unit::query()
                    ->select('id', 'unit_name', 'slug', 'description')
                    ->orderBy('unit_name')
                    ->get()
            ),
        ]);
    }
}
