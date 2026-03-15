<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    private const MILES_TO_KM = 1.60934;
    private const METERS_TO_FEET = 3.28084;

    protected $fillable = [
        'name',
        'email',
        'password',
        'timezone',
        'distance_unit',
        'temperature_unit',
        'elevation_unit',
        'currency',
        'theme',
        'debug_mode',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'debug_mode' => 'boolean',
        ];
    }

    public function convertTemp(?float $celsius): ?float
    {
        if ($celsius === null) {
            return null;
        }

        return $this->temperature_unit === 'F' ? $celsius * 9 / 5 + 32 : $celsius;
    }

    public function tempUnit(): string
    {
        return ($this->temperature_unit ?? 'C') === 'F' ? '°F' : '°C';
    }

    public function formatTemp(?float $celsius): ?string
    {
        $converted = $this->convertTemp($celsius);
        if ($converted === null) {
            return null;
        }

        return number_format($converted, 0) . $this->tempUnit();
    }

    public function usesFeet(): bool
    {
        return ($this->elevation_unit ?? 'ft') === 'ft';
    }

    public function elevationUnit(): string
    {
        return $this->usesFeet() ? 'ft' : 'm';
    }

    /**
     * Convert meters to user's preferred elevation unit.
     */
    public function convertElevation(?float $meters): ?float
    {
        if ($meters === null) {
            return null;
        }

        return $this->usesFeet() ? $meters * self::METERS_TO_FEET : $meters;
    }

    public function usesKm(): bool
    {
        return ($this->distance_unit ?? 'mi') === 'km';
    }

    public function distanceUnit(): string
    {
        return $this->usesKm() ? 'km' : 'mi';
    }

    public function speedUnit(): string
    {
        return $this->usesKm() ? 'km/h' : 'mph';
    }

    public function efficiencyUnit(): string
    {
        return $this->usesKm() ? 'Wh/km' : 'Wh/mi';
    }

    public function efficiencyUnitAlt(): string
    {
        return $this->usesKm() ? 'km/kWh' : 'mi/kWh';
    }

    /**
     * Convert miles to user's preferred distance unit.
     */
    public function convertDistance(?float $miles): ?float
    {
        return $this->convertImperialDistance($miles);
    }

    /**
     * Convert mph to user's preferred speed unit.
     */
    public function convertSpeed(?float $mph): ?float
    {
        return $this->convertImperialDistance($mph);
    }

    /**
     * Convert Wh/mi to user's preferred efficiency unit.
     */
    public function convertEfficiency(?float $whPerMile): ?float
    {
        if ($whPerMile === null) {
            return null;
        }

        return $this->usesKm() ? $whPerMile / self::MILES_TO_KM : $whPerMile;
    }

    /**
     * Convert mi/kWh to user's preferred alt efficiency unit.
     */
    public function convertEfficiencyAlt(?float $miPerKwh): ?float
    {
        return $this->convertImperialDistance($miPerKwh);
    }

    private function convertImperialDistance(?float $value): ?float
    {
        if ($value === null) {
            return null;
        }

        return $this->usesKm() ? $value * self::MILES_TO_KM : $value;
    }

    public function userTz(): string
    {
        return $this->timezone ?? 'UTC';
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }
}
