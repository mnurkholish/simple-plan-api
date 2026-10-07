<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Asset;
use App\Repositories\Contracts\AssetRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AssetRepository implements AssetRepositoryInterface
{
    public function findById(string $id): ?Asset
    {
        return Asset::find($id);
    }

    public function getPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return Asset::with(['masterKlasifikasi', 'masterRuang', 'masterBarang'])->paginate($perPage);
    }

    public function create(array $data): Asset
    {
        return Asset::create($data);
    }

    public function update(Asset $asset, array $data): bool
    {
        return $asset->update($data);
    }
}
