<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChargePoint extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'charge_id',
        'timestamp',
        'battery_level',
        'charger_power_kw',
        'voltage',
        'current',
        'rated_range',
    ];

    protected function casts(): array
    {
        return [
            'timestamp' => 'datetime',
        ];
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }
}
