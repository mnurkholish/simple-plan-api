<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'unit_name',
        'description',
        'slug',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'unit_id');
    }
}
