<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleState extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'vehicle_id',
        'timestamp',
        'state',
        'latitude',
        'longitude',
        'heading',
        'elevation',
        'speed',
        'power',
        'battery_level',
        'rated_range',
        'ideal_range',
        'odometer',
        'inside_temp',
        'outside_temp',
        'locked',
        'sentry_mode',
        'climate_on',
        'gear',
        'charger_power',
        'charger_voltage',
        'charger_current',
        'charge_limit_soc',
        'charge_state',
        'energy_remaining',
        'software_version',
    ];

    protected function casts(): array
    {
        return [
            'timestamp' => 'datetime',
            'created_at' => 'datetime',
            'locked' => 'boolean',
            'sentry_mode' => 'boolean',
            'climate_on' => 'boolean',
            'battery_level' => 'integer',
            'rated_range' => 'float',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
