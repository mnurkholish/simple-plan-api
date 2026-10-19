<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetItem extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if ($model->code === null || $model->code === '') {
                $maxCode = (int) static::query()
                    ->where('asset_classification_id', $model->asset_classification_id)
                    ->max('code');
                $model->code = $maxCode + 1;
            }
        });
    }

    protected $fillable = [
        'asset_classification_id',
        'code',
        'name',
    ];

    public function classification(): BelongsTo
    {
        return $this->belongsTo(AssetClassification::class, 'asset_classification_id');
    }

    public function assetCodes(): HasMany
    {
        return $this->hasMany(AssetCode::class, 'asset_item_id');
    }
}
