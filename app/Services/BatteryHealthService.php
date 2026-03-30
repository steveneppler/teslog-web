<?php

namespace App\Services;

use App\Models\VehicleState;

class BatteryHealthService
{
    /**
     * Find the best VehicleState for battery health recording on a given day.
     * Prefers 100% SOC, then the highest SOC >= 70%.
     */
    public function findBestState(int $vehicleId, string $startTime, string $endTime): ?VehicleState
    {
        $baseQuery = VehicleState::where('vehicle_id', $vehicleId)
            ->where('timestamp', '>=', $startTime)
            ->where('timestamp', '<=', $endTime)
            ->whereNotNull('battery_level')
            ->whereNotNull('rated_range')
            ->where('rated_range', '>', 0);

        // Prefer 100% battery
        $state = (clone $baseQuery)
            ->where('battery_level', 100)
            ->orderByDesc('timestamp')
            ->first();

        if ($state) {
            return $state;
        }

        // Fall back to highest SOC >= 70%
        return (clone $baseQuery)
            ->where('battery_level', '>=', 70)
            ->orderByDesc('battery_level')
            ->orderByDesc('timestamp')
            ->first();
    }

    /**
     * Calculate the degradation percentage for a given range and SOC,
     * compared to the best-ever recorded range at >= 95% SOC.
     */
    public function calculateDegradation(int $vehicleId, float $currentRange, int $currentLevel): ?float
    {
        $originalRange = $this->getOriginalFullRange($vehicleId);

        return $this->computeDegradation($originalRange, $currentRange, $currentLevel);
    }

    /**
     * Compute degradation from a pre-fetched original full range.
     * Use this when processing many records for the same vehicle to avoid repeated queries.
     */
    public function computeDegradation(?float $originalFullRange, float $currentRange, int $currentLevel): ?float
    {
        if ($originalFullRange === null || $originalFullRange <= 0) {
            return null;
        }

        if ($currentLevel <= 0) {
            return null;
        }
        $currentFullRange = $currentRange / $currentLevel * 100;

        return round((1 - $currentFullRange / $originalFullRange) * 100, 1);
    }

    /**
     * Get the best-ever full range (extrapolated to 100% SOC) for a vehicle.
     */
    public function getOriginalFullRange(int $vehicleId): ?float
    {
        $bestRecord = VehicleState::where('vehicle_id', $vehicleId)
            ->whereNotNull('rated_range')
            ->where('battery_level', '>=', 95)
            ->where('rated_range', '>', 0)
            ->orderByDesc('rated_range')
            ->first();

        if (! $bestRecord) {
            return null;
        }

        return $bestRecord->rated_range / $bestRecord->battery_level * 100;
    }
}
