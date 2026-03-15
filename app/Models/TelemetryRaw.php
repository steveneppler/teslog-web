<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelemetryRaw extends Model
{
    public $timestamps = false;

    protected $table = 'telemetry_raw';

    protected $fillable = [
        'vehicle_id',
        'timestamp',
        'field_name',
        'value_numeric',
        'value_string',
        'processed',
    ];

    protected function casts(): array
    {
        return [
            'timestamp' => 'datetime',
            'created_at' => 'datetime',
            'processed' => 'boolean',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
