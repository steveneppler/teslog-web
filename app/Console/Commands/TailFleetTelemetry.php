<?php

namespace App\Console\Commands;

use App\Jobs\ProcessTelemetryBatch;
use App\Models\TelemetryRaw;
use App\Models\Vehicle;
use Illuminate\Console\Command;

class TailFleetTelemetry extends Command
{
    protected $signature = 'teslog:tail-telemetry
        {logfile? : Path to fleet-telemetry log file (default: storage/logs/fleet-telemetry.log)}
        {--from-start : Process from beginning of file instead of tailing}';

    protected $description = 'Tail Fleet Telemetry logger output and ingest records into the database';

    private array $vehicleCache = [];

    public function handle(): int
    {
        $logfile = $this->argument('logfile') ?: storage_path('logs/fleet-telemetry.log');

        if (! file_exists($logfile)) {
            $this->error("Log file not found: {$logfile}");

            return self::FAILURE;
        }

        $this->info("Tailing {$logfile} for telemetry records...");

        $handle = fopen($logfile, 'r');
        if (! $this->option('from-start')) {
            // Seek to end so we only process new records
            fseek($handle, 0, SEEK_END);
        }

        $pendingVehicles = [];

        while (true) {
            $line = fgets($handle);

            if ($line === false) {
                // Flush any pending vehicles
                if (! empty($pendingVehicles)) {
                    foreach (array_keys($pendingVehicles) as $vehicleId) {
                        ProcessTelemetryBatch::dispatch($vehicleId);
                    }
                    $pendingVehicles = [];
                }

                usleep(500_000); // 500ms
                clearstatcache(false, $logfile);

                continue;
            }

            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $json = json_decode($line, true);
            if (! $json || ($json['msg'] ?? '') !== 'record_payload') {
                continue;
            }

            $vin = $json['vin'] ?? null;
            $data = $json['data'] ?? [];

            if (! $vin || empty($data)) {
                continue;
            }

            $vehicle = $this->resolveVehicle($vin);
            if (! $vehicle) {
                continue;
            }

            $timestamp = $data['CreatedAt'] ?? now()->toIso8601String();
            unset($data['CreatedAt'], $data['Vin'], $data['IsResend']);

            $rows = [];
            $now = now();

            foreach ($data as $fieldName => $value) {
                // Handle Location field which contains lat/lng
                if ($fieldName === 'Location' && is_array($value)) {
                    if (isset($value['latitude'])) {
                        $rows[] = $this->buildRow($vehicle->id, $timestamp, 'Latitude', $value['latitude'], $now);
                    }
                    if (isset($value['longitude'])) {
                        $rows[] = $this->buildRow($vehicle->id, $timestamp, 'Longitude', $value['longitude'], $now);
                    }

                    continue;
                }

                $rows[] = $this->buildRow($vehicle->id, $timestamp, $fieldName, $value, $now);
            }

            if (! empty($rows)) {
                TelemetryRaw::insert($rows);
                $pendingVehicles[$vehicle->id] = true;
                $fieldNames = implode(', ', array_column($rows, 'field_name'));
                $this->line("  [{$vin}] {$timestamp}: {$fieldNames}");
            }
        }
    }

    private function buildRow(int $vehicleId, string $timestamp, string $fieldName, mixed $value, $now): array
    {
        return [
            'vehicle_id' => $vehicleId,
            'timestamp' => $timestamp,
            'field_name' => $fieldName,
            'value_numeric' => is_numeric($value) ? (float) $value : null,
            'value_string' => ! is_numeric($value) ? (string) $value : null,
            'processed' => false,
            'created_at' => $now,
        ];
    }

    private function resolveVehicle(string $vin): ?Vehicle
    {
        if (! isset($this->vehicleCache[$vin])) {
            $this->vehicleCache[$vin] = Vehicle::where('vin', $vin)->first();
        }

        return $this->vehicleCache[$vin];
    }
}
