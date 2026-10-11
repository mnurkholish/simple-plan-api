<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'asset_code_id',
        'serial_number',
        'purchase_year',
        'status',
        'condition',
        'warranty_until',
        'notes',
        'photo_object_key',
        'photo_original_name',
        'photo_mime_type',
        'photo_size',
        'calibration_object_key',
        'calibration_original_name',
        'calibration_mime_type',
        'calibration_size',
    ];

    protected $casts = [
        'status' => AssetStatus::class,
        'condition' => AssetCondition::class,
        'warranty_until' => 'date',
    ];

    public function assetCode(): BelongsTo
    {
        return $this->belongsTo(AssetCode::class);
    }
}
