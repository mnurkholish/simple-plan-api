<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMutation extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'master_ruang_id_lama',
        'master_ruang_id_baru',
        'waktu_perubahan',
    ];

    protected $casts = [
        'waktu_perubahan' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function ruangLama(): BelongsTo
    {
        return $this->belongsTo(MasterRuang::class, 'master_ruang_id_lama');
    }

    public function ruangBaru(): BelongsTo
    {
        return $this->belongsTo(MasterRuang::class, 'master_ruang_id_baru');
    }
}
