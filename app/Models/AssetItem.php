<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetItem extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if ($model->code === null || $model->code === '') {
                $maxCode = (int) static::query()->max('code');
                $model->code = $maxCode + 1;
            }
        });
    }

    protected $fillable = [
        'code',
        'name',
    ];

    public function assetCodes(): HasMany
    {
        return $this->hasMany(AssetCode::class, 'asset_item_id');
    }
}
