<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Repositories\AssetRepository;
use Illuminate\Database\Eloquent\Collection;

class AssetService
{
    public function __construct(
        protected AssetRepository $assetRepository
    ) {}

    /**
     * @return Collection<int, Asset>
     */
    public function search(int $unitId, ?string $search): Collection
    {
        return $this->assetRepository->search($unitId, $search);
    }
}
