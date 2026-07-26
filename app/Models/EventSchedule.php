<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSchedule extends Model
{
    protected $fillable = [
        'event_id',
        'title',
        'event_date',
        'start_time',
        'end_time',
        'venue',
        'address',
        'maps_url',
        'sort_order',
    ];

    protected $casts = [
        'event_date' => 'date',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
