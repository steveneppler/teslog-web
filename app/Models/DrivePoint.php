<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrivePoint extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'drive_id',
        'timestamp',
        'latitude',
        'longitude',
        'altitude',
        'speed',
        'power',
        'battery_level',
        'heading',
    ];

    protected function casts(): array
    {
        return [
            'timestamp' => 'datetime',
        ];
    }

    public function drive(): BelongsTo
    {
        return $this->belongsTo(Drive::class);
    }
}
