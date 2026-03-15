<?php

namespace App\Jobs;

use App\Enums\ChargeType;
use App\Events\ChargeCompleted;
use App\Models\Charge;
use App\Models\ChargePoint;
use App\Models\Vehicle;
use App\Models\VehicleState;
use App\Services\ChargeCostService;
use App\Services\GeocodingService;
use App\Services\PlaceMatchingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessChargeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $vehicleId,
        public \DateTimeInterface $endedAt,
    ) {}

    public function handle(GeocodingService $geocoding, PlaceMatchingService $placeMatching, ChargeCostService $chargeCost): void
    {
        $vehicle = Vehicle::find($this->vehicleId);
        if (! $vehicle) {
            return;
        }

        // Find the most recent charging state at or before endedAt
        $lastCharging = VehicleState::where('vehicle_id', $this->vehicleId)
            ->where('state', 'charging')
            ->where('timestamp', '<=', $this->endedAt)
            ->orderByDesc('timestamp')
            ->first();

        if (! $lastCharging) {
            return;
        }

        // Find the last non-charging state before this charging block
        $lastNonCharging = VehicleState::where('vehicle_id', $this->vehicleId)
            ->where('timestamp', '<', $lastCharging->timestamp)
            ->where('state', '!=', 'charging')
            ->orderByDesc('timestamp')
            ->first();

        $lookbackStart = $lastNonCharging?->timestamp ?? $lastCharging->timestamp->copy()->subHours(24);

        // Get all charging states in this session
        $chargingStates = VehicleState::where('vehicle_id', $this->vehicleId)
            ->where('state', 'charging')
            ->where('timestamp', '>', $lookbackStart)
            ->where('timestamp', '<=', $this->endedAt)
            ->orderBy('timestamp')
            ->get();

        if ($chargingStates->isEmpty()) {
            return;
        }

        $first = $chargingStates->first();
        $last = $chargingStates->last();

        // Discard trivial charges (e.g. brief charger handshake when plugging in)
        $durationMinutes = $first->timestamp->diffInMinutes($this->endedAt);
        if ($durationMinutes < 2) {
            return;
        }

        // Discard preconditioning sessions: climate draws wall power but battery
        // doesn't actually charge. Detected when battery level stays flat and
        // climate is on throughout.
        $batteryIncreased = $first->battery_level !== null
            && $last->battery_level !== null
            && $last->battery_level > $first->battery_level + 0.5;
        $climateOnThroughout = $chargingStates->every(fn ($s) => $s->climate_on);
        $maxPower = $chargingStates->max('charger_power');

        if (! $batteryIncreased && $climateOnThroughout && $maxPower < 5) {
            return;
        }

        // The transition state (first non-charging state after the session) often
        // has the true final battery level, since the last charging state is recorded
        // moments before the charger reports completion.
        $transitionState = VehicleState::where('vehicle_id', $this->vehicleId)
            ->where('timestamp', '>', $last->timestamp)
            ->where('timestamp', '<=', $last->timestamp->copy()->addMinutes(5))
            ->where('state', '!=', 'charging')
            ->orderBy('timestamp')
            ->first();

        $endBatteryLevel = $transitionState?->battery_level ?? $last->battery_level;
        $endRatedRange = $transitionState?->rated_range ?? $last->rated_range;
        $endEnergyRemaining = $transitionState?->energy_remaining ?? $last->energy_remaining;

        // Check if there's a recent charge we should extend instead of creating a new one
        // (handles brief charging blips after the main charge completes)
        $recentCharge = Charge::where('vehicle_id', $this->vehicleId)
            ->where('ended_at', '>=', $first->timestamp->copy()->subMinutes(30))
            ->where('ended_at', '<', $first->timestamp)
            ->orderByDesc('ended_at')
            ->first();

        if ($recentCharge) {
            // Extend the existing charge
            $recentCharge->update([
                'ended_at' => $this->endedAt,
                'end_battery_level' => $endBatteryLevel,
                'end_rated_range' => $endRatedRange,
                'energy_added_kwh' => $recentCharge->energy_added_kwh
                    ? $recentCharge->energy_added_kwh + ($first->energy_remaining && $endEnergyRemaining ? $endEnergyRemaining - $first->energy_remaining : 0)
                    : null,
                'max_charger_power' => max($recentCharge->max_charger_power ?? 0, $chargingStates->max('charger_power') ?? 0) ?: null,
            ]);

            // Add new charge points to existing charge
            $this->insertChargePoints($chargingStates, $recentCharge->id);

            return;
        }

        // Determine charge type
        $maxPower = $chargingStates->max('charger_power');
        $chargeType = ChargeType::Ac;
        if ($maxPower && $maxPower > 50) {
            $chargeType = ChargeType::Supercharger;
        } elseif ($maxPower && $maxPower > 20) {
            $chargeType = ChargeType::Dc;
        }

        // Energy added (battery-side, from EnergyRemaining telemetry)
        $energyAdded = null;
        if ($first->energy_remaining !== null && $endEnergyRemaining !== null) {
            $energyAdded = $endEnergyRemaining - $first->energy_remaining;
        }

        // Energy used (wall-side, from charger power × time intervals)
        $energyUsed = $this->calculateEnergyUsed($chargingStates);

        // Charging efficiency
        $chargingEfficiency = ($energyAdded && $energyUsed && $energyUsed > 0)
            ? round($energyAdded / $energyUsed * 100, 1)
            : null;

        // Voltage and current stats from charging states
        $voltages = $chargingStates->pluck('charger_voltage')->filter()->values();
        $currents = $chargingStates->pluck('charger_current')->filter()->values();

        $avgVoltage = $voltages->isNotEmpty() ? round($voltages->avg(), 2) : null;
        $maxVoltage = $voltages->isNotEmpty() ? round($voltages->max(), 2) : null;
        $avgCurrent = $currents->isNotEmpty() ? round($currents->avg(), 2) : null;
        $maxCurrent = $currents->isNotEmpty() ? round($currents->max(), 2) : null;

        // Odometer from first charging state (or nearest state with odometer)
        $odometer = $chargingStates->pluck('odometer')->filter()->first();

        // Get location — fall back to nearest state with coordinates if charging states lack them
        $lat = $first->latitude;
        $lng = $first->longitude;
        if (! $lat || ! $lng) {
            $stateWithLocation = $chargingStates->first(fn ($s) => $s->latitude && $s->longitude);
            if (! $stateWithLocation) {
                $stateWithLocation = VehicleState::where('vehicle_id', $this->vehicleId)
                    ->where('timestamp', '<=', $first->timestamp)
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->orderByDesc('timestamp')
                    ->first();
            }
            $lat = $stateWithLocation?->latitude;
            $lng = $stateWithLocation?->longitude;
        }

        $address = $geocoding->reverse($lat, $lng);
        $place = $placeMatching->findMatch($vehicle->user_id, $lat, $lng);

        $charge = Charge::create([
            'vehicle_id' => $this->vehicleId,
            'started_at' => $first->timestamp,
            'ended_at' => $this->endedAt,
            'charge_type' => $chargeType,
            'energy_added_kwh' => $energyAdded,
            'energy_used_kwh' => $energyUsed,
            'charging_efficiency' => $chargingEfficiency,
            'start_battery_level' => $first->battery_level,
            'end_battery_level' => $endBatteryLevel,
            'start_rated_range' => $first->rated_range,
            'end_rated_range' => $endRatedRange,
            'max_charger_power' => $maxPower,
            'avg_voltage' => $avgVoltage,
            'max_voltage' => $maxVoltage,
            'avg_current' => $avgCurrent,
            'max_current' => $maxCurrent,
            'odometer' => $odometer,
            'latitude' => $lat,
            'longitude' => $lng,
            'address' => $address,
            'place_id' => $place?->id,
        ]);

        // Create charge curve points
        $this->insertChargePoints($chargingStates, $charge->id);

        // Calculate cost from place pricing
        if ($place) {
            $userTz = $vehicle->user?->timezone ?? 'UTC';
            $chargeCost->calculateCost($charge, $userTz);
        }

        ChargeCompleted::dispatch($vehicle, $charge);
    }

    /**
     * Calculate wall-side energy used by integrating charger power over time intervals.
     */
    private function calculateEnergyUsed($chargingStates): ?float
    {
        if ($chargingStates->count() < 2) {
            return null;
        }

        $totalKwh = 0;
        $previous = null;

        foreach ($chargingStates as $state) {
            if ($previous && $state->charger_power && $state->charger_power > 0) {
                $intervalHours = $previous->timestamp->diffInSeconds($state->timestamp) / 3600;
                // Use average of adjacent power readings for trapezoidal integration
                $avgPower = (($previous->charger_power ?? 0) + $state->charger_power) / 2;
                $totalKwh += $avgPower * $intervalHours;
            }
            $previous = $state;
        }

        return $totalKwh > 0 ? round($totalKwh, 2) : null;
    }

    private function insertChargePoints($chargingStates, int $chargeId): void
    {
        $points = $chargingStates->map(function ($s) use ($chargeId) {
            $power = $s->charger_power;
            if ((!$power || $power == 0) && $s->charger_voltage && $s->charger_current) {
                $power = round(($s->charger_voltage * $s->charger_current) / 1000, 2);
            }

            return [
                'charge_id' => $chargeId,
                'timestamp' => $s->timestamp,
                'battery_level' => $s->battery_level,
                'charger_power_kw' => $power,
                'voltage' => $s->charger_voltage,
                'current' => $s->charger_current,
                'rated_range' => $s->rated_range,
            ];
        })->all();

        foreach (array_chunk($points, 500) as $chunk) {
            ChargePoint::insert($chunk);
        }
    }
}
