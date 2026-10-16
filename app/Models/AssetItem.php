<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
    ];

    public function assetCodes(): HasMany
    {
        return $this->hasMany(AssetCode::class, 'asset_item_id');
    }
}
