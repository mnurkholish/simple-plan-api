<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterRuang extends Model
{
    use HasFactory;

    protected $fillable = [
        'kode',
        'nama_ruang',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function mutationsLama(): HasMany
    {
        return $this->hasMany(AssetMutation::class, 'master_ruang_id_lama');
    }

    public function mutationsBaru(): HasMany
    {
        return $this->hasMany(AssetMutation::class, 'master_ruang_id_baru');
    }
}
