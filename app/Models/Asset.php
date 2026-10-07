<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'master_klasifikasi_id',
        'master_ruang_id',
        'master_barang_id',
        'no_urut',
        'no_inventaris',
        'model_spesifikasi',
        'serial_number',
        'tahun_beli',
        'status',
        'kondisi',
        'garansi_sd',
        'keterangan',
        'foto_barang',
        'dokumen_kalibrasi',
    ];

    public function masterKlasifikasi(): BelongsTo
    {
        return $this->belongsTo(MasterKlasifikasi::class);
    }

    public function masterRuang(): BelongsTo
    {
        return $this->belongsTo(MasterRuang::class);
    }

    public function masterBarang(): BelongsTo
    {
        return $this->belongsTo(MasterBarang::class);
    }

    public function mutations(): HasMany
    {
        return $this->hasMany(AssetMutation::class);
    }
}
