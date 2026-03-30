<?php

namespace App\Console\Commands;

use App\Models\BatteryHealth;
use App\Models\Vehicle;
use App\Models\VehicleState;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RecordBatteryHealth extends Command
{
    protected $signature = 'teslog:record-battery-health';

    protected $description = 'Record daily battery health snapshots for active vehicles';

    public function handle(): int
    {
        $today = Carbon::today();
        $since = Carbon::now()->subHours(24);

        $vehicles = Vehicle::where('is_active', true)->get();
        $recorded = 0;

        foreach ($vehicles as $vehicle) {
            // Skip if already recorded today
            if (BatteryHealth::where('vehicle_id', $vehicle->id)->where('recorded_at', $today)->exists()) {
                $this->line("Skipping {$vehicle->name} — already recorded today.");

                continue;
            }

            // Find best state: prefer 100% battery, then highest >= 70%
            $state = VehicleState::where('vehicle_id', $vehicle->id)
                ->where('timestamp', '>=', $since)
                ->whereNotNull('battery_level')
                ->whereNotNull('rated_range')
                ->where('rated_range', '>', 0)
                ->where('battery_level', 100)
                ->orderByDesc('timestamp')
                ->first();

            if (! $state) {
                $state = VehicleState::where('vehicle_id', $vehicle->id)
                    ->where('timestamp', '>=', $since)
                    ->whereNotNull('battery_level')
                    ->whereNotNull('rated_range')
                    ->where('rated_range', '>', 0)
                    ->where('battery_level', '>=', 70)
                    ->orderByDesc('battery_level')
                    ->orderByDesc('timestamp')
                    ->first();
            }

            if (! $state) {
                $this->line("Skipping {$vehicle->name} — no state with >= 70% SOC in last 24h.");

                continue;
            }

            $degradation = $this->calculateDegradation($vehicle->id, $state->rated_range, $state->battery_level);

            BatteryHealth::create([
                'vehicle_id' => $vehicle->id,
                'recorded_at' => $today,
                'battery_level' => $state->battery_level,
                'rated_range' => $state->rated_range,
                'ideal_range' => $state->ideal_range,
                'degradation_pct' => $degradation,
            ]);

            $recorded++;
            $this->line("Recorded {$vehicle->name}: {$state->battery_level}% / {$state->rated_range} mi" . ($degradation !== null ? " / {$degradation}% degradation" : ''));
        }

        $this->info("Done. Recorded {$recorded} battery health snapshot(s).");

        return self::SUCCESS;
    }

    private function calculateDegradation(int $vehicleId, float $currentRange, int $currentLevel): ?float
    {
        // Find the best-ever rated range at >= 95% SOC
        $bestRecord = VehicleState::where('vehicle_id', $vehicleId)
            ->whereNotNull('rated_range')
            ->where('battery_level', '>=', 95)
            ->where('rated_range', '>', 0)
            ->orderByDesc('rated_range')
            ->first();

        if (! $bestRecord) {
            return null;
        }

        // Extrapolate both to 100%
        $originalRange = $bestRecord->rated_range / $bestRecord->battery_level * 100;
        $currentFullRange = $currentRange / $currentLevel * 100;

        if ($originalRange <= 0) {
            return null;
        }

        return round((1 - $currentFullRange / $originalRange) * 100, 1);
    }
}
