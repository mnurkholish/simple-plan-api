<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Asset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AssetRepositoryInterface
{
    public function findById(string $id): ?Asset;
    public function getPaginated(int $perPage = 15): LengthAwarePaginator;
    public function create(array $data): Asset;
    public function update(Asset $asset, array $data): bool;
}
