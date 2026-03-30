<?php

namespace App\Console\Commands;

use App\Models\BatteryHealth;
use App\Models\Vehicle;
use App\Services\BatteryHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RecordBatteryHealth extends Command
{
    protected $signature = 'teslog:record-battery-health';

    protected $description = 'Record daily battery health snapshots for active vehicles';

    public function handle(BatteryHealthService $service): int
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

            $state = $service->findBestState($vehicle->id, $since, Carbon::now());

            if (! $state) {
                $this->line("Skipping {$vehicle->name} — no state with >= 70% SOC in last 24h.");

                continue;
            }

            $degradation = $service->calculateDegradation($vehicle->id, $state->rated_range, $state->battery_level);

            BatteryHealth::create([
                'vehicle_id' => $vehicle->id,
                'recorded_at' => $today,
                'battery_level' => $state->battery_level,
                'rated_range' => $state->rated_range,
                'ideal_range' => $state->ideal_range,
                'degradation_pct' => $degradation,
            ]);

            $recorded++;
            $this->line("Recorded {$vehicle->name}: {$state->battery_level}% / {$state->rated_range} mi".($degradation !== null ? " / {$degradation}% degradation" : ''));
        }

        $this->info("Done. Recorded {$recorded} battery health snapshot(s).");

        return self::SUCCESS;
    }
}
