<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Asset extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (self $asset): void {
            $asset->asset_uid = (string) Str::ulid();
        });

        static::updating(function (self $asset): void {
            if ($asset->isDirty('asset_uid')) {
                $asset->asset_uid = $asset->getOriginal('asset_uid');
            }
        });
    }

    protected $fillable = [
        'asset_code_id',
        'detail_item',
        'brand',
        'model_specification',
        'location_detail',
        'serial_number',
        'purchase_year',
        'status',
        'condition',
        'warranty_until',
        'notes',
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

    public function histories(): HasMany
    {
        return $this->hasMany(AssetHistory::class)->latest('id');
    }
}
