<?php

namespace App\Console\Commands;

use App\Models\Drive;
use App\Models\Vehicle;
use Illuminate\Console\Command;

class BackfillDriveEfficiency extends Command
{
    protected $signature = 'teslog:backfill-efficiency
        {--vehicle= : Specific vehicle ID to backfill}
        {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Backfill energy_used_kwh and efficiency for drives missing these values (e.g. TeslaFi imports). Distance column is stored in miles; efficiency is calculated as Wh/mi.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $query = Vehicle::query();
        if ($vehicleId = $this->option('vehicle')) {
            $query->where('id', $vehicleId);
        }

        $vehicles = $query->get();
        $totalUpdated = 0;
        $totalSkipped = 0;

        foreach ($vehicles as $vehicle) {
            $capacity = $vehicle->battery_capacity_kwh;

            if (! $capacity || $capacity <= 0) {
                $this->warn("Skipping {$vehicle->name} — no battery_capacity_kwh set.");
                continue;
            }

            $this->info("Processing {$vehicle->name} (ID: {$vehicle->id}, {$capacity} kWh)...");

            $updated = 0;
            $skipped = 0;
            $updates = [];

            Drive::where('vehicle_id', $vehicle->id)
                ->where(function ($q) {
                    $q->whereNull('efficiency')->orWhere('energy_used_kwh', 0);
                })
                ->where('distance', '>', 0.5)
                ->whereNotNull('start_battery_level')
                ->whereNotNull('end_battery_level')
                ->chunkById(500, function ($drives) use ($capacity, $dryRun, &$updated, &$skipped, &$updates) {
                    foreach ($drives as $drive) {
                        $batteryDrop = $drive->start_battery_level - $drive->end_battery_level;

                        if ($batteryDrop <= 0) {
                            $skipped++;
                            continue;
                        }

                        $energyKwh = $batteryDrop / 100 * $capacity;
                        $efficiencyWhPerMi = ($energyKwh * 1000) / $drive->distance;

                        // Sanity check: skip clearly bad values (< 50 or > 1000 Wh/mi)
                        if ($efficiencyWhPerMi < 50 || $efficiencyWhPerMi > 1000) {
                            $skipped++;
                            continue;
                        }

                        if ($dryRun) {
                            $this->line("  Would update drive {$drive->id}: {$batteryDrop}% drop → {$energyKwh} kWh, " . round($efficiencyWhPerMi, 0) . ' Wh/mi');
                        } else {
                            $drive->update([
                                'energy_used_kwh' => round($energyKwh, 2),
                                'efficiency' => round($efficiencyWhPerMi, 2),
                            ]);
                        }

                        $updated++;
                    }
                });

            $totalUpdated += $updated;
            $totalSkipped += $skipped;
            $this->line('  ' . ($dryRun ? 'Would update' : 'Updated') . " {$updated} drives, skipped {$skipped}.");
        }

        $label = $dryRun ? 'Would update' : 'Updated';
        $this->info("Done. {$label} {$totalUpdated} drive(s), skipped {$totalSkipped}.");

        return self::SUCCESS;
    }
}
