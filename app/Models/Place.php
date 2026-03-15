<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Place extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'name',
        'latitude',
        'longitude',
        'radius_meters',
        'electricity_cost_per_kwh',
        'auto_tag',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function touRates(): HasMany
    {
        return $this->hasMany(PlaceTouRate::class);
    }
}
