<?php

namespace App\Console\Commands;

use App\Models\BatteryHealth;
use App\Models\Vehicle;
use App\Models\VehicleState;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class BackfillBatteryHealth extends Command
{
    protected $signature = 'teslog:backfill-battery-health {--vehicle= : Specific vehicle ID to backfill}';

    protected $description = 'Backfill battery health records from historical VehicleState data';

    public function handle(): int
    {
        $query = Vehicle::query();

        if ($vehicleId = $this->option('vehicle')) {
            $query->where('id', $vehicleId);
        }

        $vehicles = $query->get();
        $totalCreated = 0;

        foreach ($vehicles as $vehicle) {
            $this->info("Processing {$vehicle->name} (ID: {$vehicle->id})...");

            // Get existing record dates to skip
            $existingDates = BatteryHealth::where('vehicle_id', $vehicle->id)
                ->pluck('recorded_at')
                ->map(fn ($d) => $d->format('Y-m-d'))
                ->flip();

            // Find best-ever range at high SOC for degradation calculation
            $bestState = VehicleState::where('vehicle_id', $vehicle->id)
                ->whereNotNull('rated_range')
                ->where('battery_level', '>=', 95)
                ->where('rated_range', '>', 0)
                ->orderByDesc('rated_range')
                ->first();

            $originalRange = $bestState
                ? $bestState->rated_range / $bestState->battery_level * 100
                : null;

            // Process in daily chunks using raw SQL to find best reading per day
            $dailyBest = VehicleState::where('vehicle_id', $vehicle->id)
                ->whereNotNull('battery_level')
                ->whereNotNull('rated_range')
                ->where('battery_level', '>=', 70)
                ->where('rated_range', '>', 0)
                ->selectRaw("DATE(timestamp) as day, MAX(battery_level) as max_soc")
                ->groupByRaw('DATE(timestamp)')
                ->orderBy('day')
                ->get();

            if ($dailyBest->isEmpty()) {
                $this->line("  No qualifying states found. Skipping.");

                continue;
            }

            $created = 0;

            foreach ($dailyBest as $row) {
                $date = $row->day;

                if ($existingDates->has($date)) {
                    continue;
                }

                // Fetch the actual state record for this day's best SOC
                $best = VehicleState::where('vehicle_id', $vehicle->id)
                    ->whereRaw('DATE(timestamp) = ?', [$date])
                    ->where('battery_level', $row->max_soc)
                    ->whereNotNull('rated_range')
                    ->where('rated_range', '>', 0)
                    ->orderByDesc('timestamp')
                    ->first(['timestamp', 'battery_level', 'rated_range', 'ideal_range']);

                if (! $best) {
                    continue;
                }

                $degradation = null;
                if ($originalRange && $originalRange > 0) {
                    $currentFullRange = $best->rated_range / $best->battery_level * 100;
                    $degradation = round((1 - $currentFullRange / $originalRange) * 100, 1);
                }

                BatteryHealth::create([
                    'vehicle_id' => $vehicle->id,
                    'recorded_at' => Carbon::parse($date),
                    'battery_level' => $best->battery_level,
                    'rated_range' => $best->rated_range,
                    'ideal_range' => $best->ideal_range,
                    'degradation_pct' => $degradation,
                ]);

                $created++;
            }

            $totalCreated += $created;
            $this->line("  Created {$created} records.");
        }

        $this->info("Done. Created {$totalCreated} battery health record(s) total.");

        return self::SUCCESS;
    }
}
