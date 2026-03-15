<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatteryHealth extends Model
{
    use HasFactory;
    protected $table = 'battery_health';

    protected $fillable = [
        'vehicle_id',
        'recorded_at',
        'battery_level',
        'rated_range',
        'ideal_range',
        'degradation_pct',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'date',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
