<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetLocation extends Model
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
}
