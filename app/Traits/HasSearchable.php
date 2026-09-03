<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasSearchable
{
    /**
     * @param  list<string>|string  $columns
     */
    public function applySearch(Builder $query, array|string $columns = ['name']): Builder
    {
        $search = request('search');

        if (! $search) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($columns, $search): void {
            foreach ((array) $columns as $column) {
                $query->orWhere($column, 'like', "%{$search}%");
            }
        });
    }
}
