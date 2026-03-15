<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaceTouRate extends Model
{
    protected $fillable = [
        'place_id',
        'day_of_week',
        'start_time',
        'end_time',
        'rate_per_kwh',
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }
}
