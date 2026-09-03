<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\QueryBuilder\QueryBuilder;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $service) {}

    public function index()
    {
        $query = QueryBuilder::for(Role::class)
            ->allowedFilters('name')
            ->allowedSorts('name', 'created_at')
            ->allowedIncludes('permissions')
            ->with('permissions')
            ->defaultSort('-created_at');

        return RoleResource::collection($this->dynamicPaginate($query));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->service->store($request->validated());

        return (new RoleResource($role->load('permissions')))
            ->additional(['message' => 'Akses Group berhasil ditambahkan.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Role $role): RoleResource
    {
        return new RoleResource($role->load('permissions'));
    }

    public function update(UpdateRoleRequest $request, Role $role): RoleResource
    {
        $role = $this->service->update($role, $request->validated());

        return (new RoleResource($role->load('permissions')))
            ->additional(['message' => 'Akses Group berhasil diperbarui.']);
    }

    public function destroy(string $ids): JsonResponse
    {
        $this->service->destroy(explode(',', $ids));

        return response()->json([
            'message' => 'Akses Group berhasil dihapus.',
        ]);
    }

    public function permissions(): JsonResponse
    {
        return response()->json([
            'data' => Permission::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
        ]);
    }
}
