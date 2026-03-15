<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Idle extends Model
{
    use HasFactory;
    protected $fillable = [
        'vehicle_id',
        'started_at',
        'ended_at',
        'latitude',
        'longitude',
        'address',
        'place_id',
        'start_battery_level',
        'end_battery_level',
        'vampire_drain_rate',
        'sentry_mode_active',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'sentry_mode_active' => 'boolean',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }
}
