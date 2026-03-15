<?php

namespace App\Console\Commands;

use App\Models\FirmwareHistory;
use App\Models\Vehicle;
use App\Models\VehicleState;
use Illuminate\Console\Command;

class BackfillFirmwareHistory extends Command
{
    protected $signature = 'teslog:backfill-firmware-history {--vehicle= : Specific vehicle ID to backfill}';

    protected $description = 'Backfill firmware history from software_version changes in vehicle_states';

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

            // Get existing firmware records to avoid duplicates
            $existingVersions = FirmwareHistory::where('vehicle_id', $vehicle->id)
                ->pluck('version')
                ->flip();

            // Find distinct software versions in chronological order
            // by getting the first occurrence of each version
            $versionChanges = [];
            $lastVersion = null;

            VehicleState::where('vehicle_id', $vehicle->id)
                ->whereNotNull('software_version')
                ->where('software_version', '!=', '')
                ->orderBy('timestamp')
                ->select(['timestamp', 'software_version'])
                ->chunk(5000, function ($states) use (&$versionChanges, &$lastVersion) {
                    foreach ($states as $state) {
                        if ($state->software_version !== $lastVersion) {
                            $versionChanges[] = [
                                'version' => $state->software_version,
                                'detected_at' => $state->timestamp,
                                'previous_version' => $lastVersion,
                            ];
                            $lastVersion = $state->software_version;
                        }
                    }
                });

            if (empty($versionChanges)) {
                $this->line("  No software versions found. Skipping.");

                continue;
            }

            $created = 0;

            foreach ($versionChanges as $change) {
                if ($existingVersions->has($change['version'])) {
                    continue;
                }

                FirmwareHistory::create([
                    'vehicle_id' => $vehicle->id,
                    'version' => $change['version'],
                    'detected_at' => $change['detected_at'],
                    'previous_version' => $change['previous_version'],
                ]);

                $created++;
            }

            // Update vehicle's current firmware_version to the latest
            if (! empty($versionChanges)) {
                $latestVersion = end($versionChanges)['version'];
                if ($vehicle->firmware_version !== $latestVersion) {
                    $vehicle->update(['firmware_version' => $latestVersion]);
                    $this->line("  Updated vehicle firmware_version to {$latestVersion}");
                }
            }

            $totalCreated += $created;
            $this->line("  Created {$created} firmware history records.");
        }

        $this->info("Done. Created {$totalCreated} firmware history record(s) total.");

        return self::SUCCESS;
    }
}
