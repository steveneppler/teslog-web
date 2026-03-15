<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Vehicle extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'tesla_vehicle_id',
        'vin',
        'name',
        'model',
        'trim',
        'battery_capacity_kwh',
        'color',
        'firmware_version',
        'is_active',
        'show_on_dashboard',
        'tesla_access_token',
        'tesla_refresh_token',
        'tesla_token_expires_at',
        'latest_state_id',
    ];

    protected $hidden = [
        'tesla_access_token',
        'tesla_refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_on_dashboard' => 'boolean',
            'tesla_access_token' => 'encrypted',
            'tesla_refresh_token' => 'encrypted',
            'tesla_token_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function latestState(): BelongsTo
    {
        return $this->belongsTo(VehicleState::class, 'latest_state_id');
    }

    public function states(): HasMany
    {
        return $this->hasMany(VehicleState::class);
    }

    public function drives(): HasMany
    {
        return $this->hasMany(Drive::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    public function idles(): HasMany
    {
        return $this->hasMany(Idle::class);
    }

    public function batteryHealth(): HasMany
    {
        return $this->hasMany(BatteryHealth::class);
    }

    public function firmwareHistory(): HasMany
    {
        return $this->hasMany(FirmwareHistory::class);
    }

    public function telemetryRaw(): HasMany
    {
        return $this->hasMany(TelemetryRaw::class);
    }

    public function commandLogs(): HasMany
    {
        return $this->hasMany(CommandLog::class);
    }
}
