<?php

namespace App\Models;

use App\Enums\ChargeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Charge extends Model
{
    use HasFactory;
    protected $fillable = [
        'vehicle_id',
        'started_at',
        'ended_at',
        'charge_type',
        'energy_added_kwh',
        'energy_used_kwh',
        'charging_efficiency',
        'cost',
        'start_battery_level',
        'end_battery_level',
        'start_rated_range',
        'end_rated_range',
        'max_charger_power',
        'avg_voltage',
        'max_voltage',
        'avg_current',
        'max_current',
        'odometer',
        'latitude',
        'longitude',
        'address',
        'place_id',
        'tag',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'charge_type' => ChargeType::class,
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

    public function points(): HasMany
    {
        return $this->hasMany(ChargePoint::class)->orderBy('timestamp');
    }
}
