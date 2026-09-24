<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketTikDetail extends Model
{
    protected $primaryKey = 'ticket_id';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'quality_category_id',
        'it_tag_id',
        'custom_it_tag_text',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function qualityCategory(): BelongsTo
    {
        return $this->belongsTo(QualityCategory::class);
    }

    public function itTag(): BelongsTo
    {
        return $this->belongsTo(ItTag::class);
    }
}
