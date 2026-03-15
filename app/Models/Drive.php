<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Drive extends Model
{
    use HasFactory;
    protected $fillable = [
        'vehicle_id',
        'started_at',
        'ended_at',
        'distance',
        'energy_used_kwh',
        'efficiency',
        'start_latitude',
        'start_longitude',
        'end_latitude',
        'end_longitude',
        'start_address',
        'end_address',
        'start_place_id',
        'end_place_id',
        'start_battery_level',
        'end_battery_level',
        'start_rated_range',
        'end_rated_range',
        'start_odometer',
        'end_odometer',
        'max_speed',
        'avg_speed',
        'outside_temp_avg',
        'tag',
        'notes',
        'weather',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'weather' => 'array',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function startPlace(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'start_place_id');
    }

    public function endPlace(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'end_place_id');
    }

    public function points(): HasMany
    {
        return $this->hasMany(DrivePoint::class)->orderBy('timestamp');
    }
}
