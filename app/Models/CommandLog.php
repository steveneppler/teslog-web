<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommandLog extends Model
{
    protected $fillable = [
        'vehicle_id',
        'user_id',
        'command',
        'parameters',
        'success',
        'error_message',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'success' => 'boolean',
            'executed_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
