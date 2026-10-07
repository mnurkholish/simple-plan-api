<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterBarang extends Model
{
    use HasFactory;

    protected $fillable = [
        'kode',
        'nama_barang',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
