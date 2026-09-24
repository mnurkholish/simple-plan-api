<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityCategory extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function ticketTikDetails(): HasMany
    {
        return $this->hasMany(TicketTikDetail::class);
    }
}
