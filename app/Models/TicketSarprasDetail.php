<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketSarprasDetail extends Model
{
    protected $primaryKey = 'ticket_id';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sarpras_category_id',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function sarprasCategory(): BelongsTo
    {
        return $this->belongsTo(SarprasCategory::class);
    }
}
