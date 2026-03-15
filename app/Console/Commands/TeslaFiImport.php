<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Services\TeslaFiImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TeslaFiImport extends Command
{
    protected $signature = 'teslog:import:teslafi
        {--type= : Import type: raw, drives, or charges}
        {--vehicle= : Vehicle ID}
        {--timezone= : Timezone TeslaFi was configured in (e.g. America/Chicago)}
        {--path= : Path to CSV file or directory}
        {--units=mi : Distance units in the CSV: mi or km}';

    protected $description = 'Import TeslaFi CSV data into Teslog';

    public function handle(TeslaFiImportService $service): int
    {
        $type = $this->option('type');
        $vehicleId = $this->option('vehicle');
        $timezone = $this->option('timezone');
        $path = $this->option('path');
        $units = $this->option('units');

        // Validate required options
        if (! $type || ! in_array($type, ['raw', 'drives', 'charges'])) {
            $this->error('--type is required and must be one of: raw, drives, charges');
            return self::FAILURE;
        }

        if (! $vehicleId) {
            $this->error('--vehicle is required');
            return self::FAILURE;
        }

        if (! $path) {
            $this->error('--path is required');
            return self::FAILURE;
        }

        // Validate vehicle exists
        $vehicle = Vehicle::find($vehicleId);
        if (! $vehicle) {
            $this->error("Vehicle with ID {$vehicleId} not found.");
            return self::FAILURE;
        }

        // Default timezone from vehicle owner's settings
        if (! $timezone) {
            $timezone = $vehicle->user->timezone ?? 'UTC';
            $this->info("Using timezone from user settings: {$timezone}");
        }

        // Collect CSV files
        $files = [];
        if (is_dir($path)) {
            $csvFiles = File::glob(rtrim($path, '/') . '/*.csv');
            sort($csvFiles); // alphabetical = chronological for TeslaFi naming
            $files = $csvFiles;
            if (empty($files)) {
                $this->error("No CSV files found in directory: {$path}");
                return self::FAILURE;
            }
            $this->info("Found " . count($files) . " CSV file(s) in directory.");
        } elseif (is_file($path)) {
            $files = [$path];
        } else {
            $this->error("Path does not exist: {$path}");
            return self::FAILURE;
        }

        $totalImported = 0;
        $totalSkipped = 0;
        $allErrors = [];

        $bar = $this->output->createProgressBar(count($files));
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- %message%');
        $bar->setMessage('Starting...');
        $bar->start();

        foreach ($files as $file) {
            $bar->setMessage(basename($file));

            $result = match ($type) {
                'raw' => $service->importRawData($file, (int) $vehicleId, $timezone),
                'drives' => $service->importDrives($file, (int) $vehicleId, $timezone, $units),
                'charges' => $service->importCharges($file, (int) $vehicleId, $timezone),
            };

            $totalImported += $result['imported'];
            $totalSkipped += $result['skipped'];

            foreach ($result['errors'] as $error) {
                $allErrors[] = basename($file) . ': ' . $error;
            }

            $bar->advance();
        }

        $bar->setMessage('Done');
        $bar->finish();
        $this->newLine(2);

        // Summary
        $this->info("Import complete.");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Imported', $totalImported],
                ['Skipped (duplicates)', $totalSkipped],
                ['Errors', count($allErrors)],
            ]
        );

        if (! empty($allErrors)) {
            $this->newLine();
            $this->warn('Errors:');
            foreach (array_slice($allErrors, 0, 50) as $error) {
                $this->line("  - {$error}");
            }
            if (count($allErrors) > 50) {
                $this->line("  ... and " . (count($allErrors) - 50) . " more errors.");
            }
        }

        return self::SUCCESS;
    }
}
