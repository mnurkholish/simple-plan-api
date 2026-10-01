<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchUnitsRequest;
use App\Http\Requests\SyncUnitUsersRequest;
use App\Http\Requests\UnitRequest;
use App\Http\Resources\UnitResource;
use App\Http\Resources\UnitSearchResource;
use App\Http\Resources\UserResource;
use App\Models\Unit;
use App\Models\User;
use App\Services\UnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\QueryBuilder;

class UnitController extends Controller
{
    public function __construct(private readonly UnitService $service) {}

    public function index()
    {
        $baseQuery = $this->applySearch(
            Unit::query(),
            ['unit_name', 'description'],
        );

        $query = QueryBuilder::for($baseQuery)
            ->allowedFilters('unit_name')
            ->allowedSorts('unit_name', 'created_at')
            ->allowedIncludes('users')
            ->with('users:id,name,email,avatar,nip,status')
            ->withCount('users')
            ->defaultSort('unit_name');

        $user = auth()->user();

        if (! $user->hasPermissionTo('units-access-all')) {
            $query->whereHas('users', function ($query) use ($user): void {
                $query->where('users.id', $user->id);
            });
        }

        return UnitResource::collection($this->dynamicPaginate($query));
    }

    public function search(SearchUnitsRequest $request): AnonymousResourceCollection
    {
        return UnitSearchResource::collection(
            $this->service->search($request->string('search')->toString() ?: null)
        );
    }

    public function store(UnitRequest $request): JsonResponse
    {
        $unit = $this->service->store($request->validated());

        return (new UnitResource($unit->load('users')))
            ->additional(['message' => 'Departemen berhasil ditambahkan.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Unit $unit): UnitResource
    {
        return new UnitResource($unit->load('users')->loadCount('users'));
    }

    public function update(UnitRequest $request, Unit $unit): UnitResource
    {
        $this->authorize('update', $unit);

        $unit = $this->service->update($unit, $request->validated());

        return (new UnitResource($unit->load('users')->loadCount('users')))
            ->additional(['message' => 'Departemen berhasil diperbarui.']);
    }

    public function destroy(Unit $unit): JsonResponse
    {
        $this->authorize('delete', $unit);

        $this->service->destroy([$unit->id]);

        return response()->json([
            'message' => 'Departemen berhasil dihapus.',
        ]);
    }

    public function syncUsers(SyncUnitUsersRequest $request, Unit $unit): JsonResponse
    {
        $this->authorize('update', $unit);

        $validated = $request->validated();
        $userIds = $validated['user_ids'] ?? [];

        $this->service->syncUsers($unit, $userIds);

        return response()->json([
            'message' => 'Pengguna departemen berhasil diperbarui.',
            'user_ids' => $userIds,
            'unit' => new UnitResource($unit->load('users')->loadCount('users')),
        ]);
    }

    public function users(): JsonResponse
    {
        return response()->json([
            'data' => UserResource::collection(
                User::query()
                    ->with(['roles:id,name', 'unit'])
                    ->orderBy('name')
                    ->get()
            ),
        ]);
    }
}
