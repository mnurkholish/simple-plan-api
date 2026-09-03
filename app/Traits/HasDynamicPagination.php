<?php

namespace App\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

trait HasDynamicPagination
{
    public function dynamicPaginate(mixed $query, int $defaultPerPage = 10): LengthAwarePaginator
    {
        $perPage = request('per_page', $defaultPerPage);

        if ($perPage === 'all') {
            $count = $query->count();
            $perPage = $count > 0 ? min($count, config('app.max_per_page', 500)) : 1;
        }

        return $query->paginate((int) $perPage)->withQueryString();
    }
}
