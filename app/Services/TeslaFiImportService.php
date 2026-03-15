<?php

namespace App\Services;

use App\Enums\ChargeType;
use App\Models\Charge;
use App\Models\Drive;
use App\Models\Vehicle;
use App\Models\VehicleState;
use Carbon\Carbon;
use Generator;
use Illuminate\Support\Facades\DB;

class TeslaFiImportService
{
    /**
     * Import raw TeslaFi data CSV into vehicle_states.
     */
    public function importRawData(string $filePath, int $vehicleId, string $timezone, ?\Closure $onProgress = null): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        // Track timestamps we've seen in this import to avoid self-duplicates
        $seenTimestamps = [];

        $batch = [];

        foreach ($this->parseCsvWithBom($filePath) as $lineNumber => $row) {
            try {
                $timestamp = $this->convertTimezone($row['Date'] ?? '', $timezone);
                if (! $timestamp) {
                    $errors[] = "Line {$lineNumber}: Missing or invalid Date.";
                    continue;
                }

                $tsString = $timestamp->format('Y-m-d H:i:s');
                if (isset($seenTimestamps[$tsString])) {
                    $skipped++;
                    continue;
                }

                // Determine state
                $state = $this->determineState($row);

                // Parse battery_level — may be float since Nov 2024
                $batteryLevel = isset($row['battery_level']) && $row['battery_level'] !== ''
                    ? (int) round((float) $row['battery_level'])
                    : null;

                // Parse charger_power — may be missing
                $chargerPower = $this->parseFloat($row['charger_power'] ?? null);
                if (($chargerPower === null || $chargerPower == 0) && isset($row['charger_actual_current'], $row['charger_voltage'])) {
                    $current = $this->parseFloat($row['charger_actual_current']);
                    $voltage = $this->parseFloat($row['charger_voltage']);
                    if ($current !== null && $voltage !== null) {
                        $chargerPower = round(($current * $voltage) / 1000, 2);
                    }
                }

                $record = [
                    'vehicle_id' => $vehicleId,
                    'timestamp' => $tsString,
                    'state' => $state,
                    'battery_level' => $batteryLevel,
                    'rated_range' => $this->parseFloat($row['rated_battery_range_km'] ?? ($row['battery_range'] ?? null)),
                    'ideal_range' => $this->parseFloat($row['ideal_battery_range_km'] ?? ($row['ideal_battery_range'] ?? null)),
                    'speed' => $this->parseFloat($row['speed'] ?? null),
                    'power' => $this->parseFloat($row['power'] ?? null),
                    'odometer' => $this->parseFloat($row['odometer'] ?? null),
                    'latitude' => $this->parseFloat($row['latitude'] ?? null),
                    'longitude' => $this->parseFloat($row['longitude'] ?? null),
                    'heading' => $this->parseFloat($row['heading'] ?? null),
                    'elevation' => $this->parseFloat($row['elevation'] ?? null),
                    'inside_temp' => $this->parseFloat($row['inside_temp'] ?? null),
                    'outside_temp' => $this->parseFloat($row['outside_temp'] ?? null),
                    'locked' => $this->parseBool($row['locked'] ?? null),
                    'sentry_mode' => $this->parseBool($row['sentry_mode'] ?? null),
                    'climate_on' => $this->parseBool($row['is_climate_on'] ?? ($row['climate_on'] ?? null)),
                    'gear' => $this->parseNullableString($row['Shift State'] ?? ($row['shift_state'] ?? null)),
                    'charger_power' => $chargerPower,
                    'charger_voltage' => $this->parseFloat($row['charger_voltage'] ?? null),
                    'charger_current' => $this->parseFloat($row['charger_actual_current'] ?? null),
                    'charge_limit_soc' => isset($row['charge_limit_soc']) && $row['charge_limit_soc'] !== ''
                        ? (int) $row['charge_limit_soc']
                        : null,
                    'charge_state' => $this->parseNullableString($row['charging_state'] ?? null),
                    'energy_remaining' => $this->parseFloat($row['usable_battery_level'] ?? null),
                    'software_version' => $this->parseNullableString($row['car_version'] ?? ($row['software_version'] ?? null)),
                ];

                $batch[] = $record;
                $seenTimestamps[$tsString] = true;
                $imported++;

                if (count($batch) >= 500) {
                    DB::table('vehicle_states')->upsert($batch, ['vehicle_id', 'timestamp']);
                    $batch = [];
                    if ($onProgress) {
                        $onProgress($imported, $skipped);
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = "Line {$lineNumber}: {$e->getMessage()}";
            }
        }

        // Insert remaining batch
        if (! empty($batch)) {
            DB::table('vehicle_states')->upsert($batch, ['vehicle_id', 'timestamp']);
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Import TeslaFi drives CSV into drives table.
     */
    public function importDrives(string $filePath, int $vehicleId, string $timezone, string $units = 'mi'): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        $existingDrives = Drive::where('vehicle_id', $vehicleId)
            ->pluck('started_at')
            ->map(fn ($ts) => $ts->format('Y-m-d H:i:s'))
            ->flip()
            ->toArray();

        DB::beginTransaction();
        try {
            foreach ($this->parseCsvWithBom($filePath) as $lineNumber => $row) {
                try {
                    // Parse start time — combine Date + Start Time, or use Date directly
                    $dateStr = trim($row['Date'] ?? '');
                    $startTimeStr = trim($row['Start Time'] ?? '');
                    $endTimeStr = trim($row['End Time'] ?? '');

                    if (! $dateStr) {
                        $errors[] = "Line {$lineNumber}: Missing Date.";
                        continue;
                    }

                    $startedAt = $this->convertTimezone(
                        $startTimeStr ? "{$dateStr} {$startTimeStr}" : $dateStr,
                        $timezone
                    );
                    $endedAt = $endTimeStr
                        ? $this->convertTimezone("{$dateStr} {$endTimeStr}", $timezone)
                        : null;

                    if (! $startedAt) {
                        $errors[] = "Line {$lineNumber}: Could not parse start time.";
                        continue;
                    }

                    // If end time is before start time, it crossed midnight
                    if ($endedAt && $endedAt->lt($startedAt)) {
                        $endedAt->addDay();
                    }

                    $startKey = $startedAt->format('Y-m-d H:i:s');
                    if (isset($existingDrives[$startKey])) {
                        $skipped++;
                        continue;
                    }

                    // Distance — column may say "Kilometers" but actually be miles
                    $distance = $this->parseFloat($row['Kilometers Driven'] ?? ($row['Miles Driven'] ?? ($row['Distance'] ?? null)));
                    if ($distance !== null && $units === 'mi') {
                        $distance = round($distance * 1.60934, 2);
                    }

                    $energyUsed = $this->parseFloat($row['kWh Used'] ?? null);
                    $efficiency = $this->parseFloat($row['Wh/Kilometer'] ?? ($row['Wh/Mile'] ?? ($row['Efficiency %'] ?? null)));
                    $avgSpeed = $this->parseFloat($row['Avg Speed'] ?? null);
                    $maxSpeed = $this->parseFloat($row['Max Speed'] ?? null);

                    $startOdometer = $this->parseFloat($row['Starting Odometer'] ?? null);
                    $endOdometer = $this->parseFloat($row['Ending Odometer'] ?? null);

                    // Battery levels — parse from "XX%" format or plain number
                    $batteryUsed = $this->parseFloat($row['Battery % Used'] ?? null);
                    $startBattery = $this->parseFloat($row['Start Battery'] ?? ($row['Starting Battery'] ?? null));
                    $endBattery = $this->parseFloat($row['End Battery'] ?? ($row['Ending Battery'] ?? null));

                    // If we have battery used but not individual levels, skip them
                    // If we have start and used, calculate end
                    if ($startBattery !== null && $endBattery === null && $batteryUsed !== null) {
                        $endBattery = $startBattery - $batteryUsed;
                    }

                    Drive::create([
                        'vehicle_id' => $vehicleId,
                        'started_at' => $startedAt,
                        'ended_at' => $endedAt,
                        'distance' => $distance,
                        'energy_used_kwh' => $energyUsed,
                        'efficiency' => $efficiency,
                        'start_address' => $this->parseNullableString($row['Starting Address'] ?? null),
                        'end_address' => $this->parseNullableString($row['Ending Address'] ?? null),
                        'start_battery_level' => $startBattery !== null ? (int) round($startBattery) : null,
                        'end_battery_level' => $endBattery !== null ? (int) round($endBattery) : null,
                        'start_odometer' => $startOdometer,
                        'end_odometer' => $endOdometer,
                        'max_speed' => $maxSpeed,
                        'avg_speed' => $avgSpeed,
                        'tag' => $this->parseNullableString($row['Tag'] ?? null),
                        'notes' => $this->parseNullableString($row['Note'] ?? ($row['Notes'] ?? null)),
                    ]);

                    $existingDrives[$startKey] = true;
                    $imported++;
                } catch (\Throwable $e) {
                    $errors[] = "Line {$lineNumber}: {$e->getMessage()}";
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $errors[] = "Transaction failed: {$e->getMessage()}";
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Import TeslaFi charges CSV into charges table.
     */
    public function importCharges(string $filePath, int $vehicleId, string $timezone): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        $existingCharges = Charge::where('vehicle_id', $vehicleId)
            ->pluck('started_at')
            ->map(fn ($ts) => $ts->format('Y-m-d H:i:s'))
            ->flip()
            ->toArray();

        DB::beginTransaction();
        try {
            foreach ($this->parseCsvWithBom($filePath) as $lineNumber => $row) {
                try {
                    $dateStr = trim($row['Date'] ?? '');
                    $startTimeStr = trim($row['Start Time'] ?? '');
                    $endTimeStr = trim($row['End Time'] ?? '');

                    if (! $dateStr) {
                        $errors[] = "Line {$lineNumber}: Missing Date.";
                        continue;
                    }

                    $startedAt = $this->convertTimezone(
                        $startTimeStr ? "{$dateStr} {$startTimeStr}" : $dateStr,
                        $timezone
                    );
                    $endedAt = $endTimeStr
                        ? $this->convertTimezone("{$dateStr} {$endTimeStr}", $timezone)
                        : null;

                    if (! $startedAt) {
                        $errors[] = "Line {$lineNumber}: Could not parse start time.";
                        continue;
                    }

                    if ($endedAt && $endedAt->lt($startedAt)) {
                        $endedAt->addDay();
                    }

                    $startKey = $startedAt->format('Y-m-d H:i:s');
                    if (isset($existingCharges[$startKey])) {
                        $skipped++;
                        continue;
                    }

                    // Determine charge type
                    $chargerPower = $this->parseFloat($row['charger_power'] ?? ($row['Max Charger Power'] ?? null));
                    $chargingState = $this->parseNullableString($row['charging_state'] ?? ($row['Charging State'] ?? null));
                    $chargeType = null;
                    if ($chargerPower !== null) {
                        $chargeType = $chargerPower > 20 ? ChargeType::Dc : ChargeType::Ac;
                    }
                    if ($chargingState && stripos($chargingState, 'Supercharging') !== false) {
                        $chargeType = ChargeType::Dc;
                    }

                    $energyAdded = $this->parseFloat($row['charge_energy_added'] ?? ($row['Energy Added'] ?? ($row['kWh Added'] ?? null)));
                    $cost = $this->parseFloat($row['Cost'] ?? ($row['Charge Cost'] ?? null));

                    $startBattery = $this->parseFloat($row['Start Battery'] ?? ($row['Starting Battery'] ?? ($row['start_battery_level'] ?? null)));
                    $endBattery = $this->parseFloat($row['End Battery'] ?? ($row['Ending Battery'] ?? ($row['battery_level'] ?? null)));

                    // If energy is missing or zero, estimate from battery percentage
                    if ((! $energyAdded || $energyAdded == 0) && $startBattery !== null && $endBattery !== null) {
                        $vehicle = Vehicle::find($vehicleId);
                        if ($vehicle && $vehicle->battery_capacity_kwh && $endBattery > $startBattery) {
                            $energyAdded = round((($endBattery - $startBattery) / 100) * $vehicle->battery_capacity_kwh, 2);
                        }
                    }

                    $latitude = $this->parseFloat($row['latitude'] ?? ($row['Latitude'] ?? null));
                    $longitude = $this->parseFloat($row['longitude'] ?? ($row['Longitude'] ?? null));
                    $address = $this->parseNullableString($row['Address'] ?? ($row['Location'] ?? null));

                    Charge::create([
                        'vehicle_id' => $vehicleId,
                        'started_at' => $startedAt,
                        'ended_at' => $endedAt,
                        'charge_type' => $chargeType,
                        'energy_added_kwh' => $energyAdded,
                        'cost' => $cost,
                        'start_battery_level' => $startBattery !== null ? (int) round($startBattery) : null,
                        'end_battery_level' => $endBattery !== null ? (int) round($endBattery) : null,
                        'max_charger_power' => $chargerPower,
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'address' => $address,
                        'tag' => $this->parseNullableString($row['Tag'] ?? null),
                        'notes' => $this->parseNullableString($row['Note'] ?? ($row['Notes'] ?? null)),
                    ]);

                    $existingCharges[$startKey] = true;
                    $imported++;
                } catch (\Throwable $e) {
                    $errors[] = "Line {$lineNumber}: {$e->getMessage()}";
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $errors[] = "Transaction failed: {$e->getMessage()}";
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Parse a CSV file, stripping UTF-8 BOM, yielding associative arrays.
     */
    public function parseCsvWithBom(string $filePath): Generator
    {
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            throw new \RuntimeException("Cannot open file: {$filePath}");
        }

        try {
            // Read first line (headers), strip BOM
            $headerLine = fgets($handle);
            if ($headerLine === false) {
                return;
            }

            // Strip UTF-8 BOM (EF BB BF)
            $headerLine = preg_replace('/^\xEF\xBB\xBF/', '', $headerLine);

            // Parse header using str_getcsv
            $headers = str_getcsv($headerLine);
            $headers = array_map('trim', $headers);
            $headerCount = count($headers);

            $lineNumber = 1;
            while (($fields = fgetcsv($handle)) !== false) {
                $lineNumber++;

                // Skip empty lines
                if (count($fields) === 1 && ($fields[0] === null || $fields[0] === '')) {
                    continue;
                }

                // Pad or trim fields to match header count
                if (count($fields) < $headerCount) {
                    $fields = array_pad($fields, $headerCount, '');
                } elseif (count($fields) > $headerCount) {
                    $fields = array_slice($fields, 0, $headerCount);
                }

                yield $lineNumber => array_combine($headers, $fields);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Convert a datetime string from user's TeslaFi timezone to UTC.
     */
    public function convertTimezone(string $datetime, string $fromTimezone): ?Carbon
    {
        $datetime = trim($datetime);
        if ($datetime === '') {
            return null;
        }

        try {
            return Carbon::parse($datetime, $fromTimezone)->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Determine vehicle state from a raw data CSV row.
     */
    protected function determineState(array $row): string
    {
        $shiftState = trim($row['Shift State'] ?? ($row['shift_state'] ?? ''));
        if (in_array(strtoupper($shiftState), ['D', 'R'], true)) {
            return 'driving';
        }

        $chargingState = trim($row['charging_state'] ?? '');
        if (stripos($chargingState, 'Charging') !== false) {
            return 'charging';
        }

        $sleepNumber = trim($row['Sleep Number'] ?? ($row['sleep_number'] ?? ''));
        if ($sleepNumber !== '' && $sleepNumber !== '0') {
            return 'sleeping';
        }

        return 'idle';
    }

    protected function parseFloat(?string $value): ?float
    {
        if ($value === null || trim($value) === '' || trim($value) === '-') {
            return null;
        }

        $cleaned = str_replace([',', '%', '$'], '', trim($value));

        if (! is_numeric($cleaned)) {
            return null;
        }

        return (float) $cleaned;
    }

    protected function parseBool(?string $value): ?bool
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $v = strtolower(trim($value));

        return in_array($v, ['1', 'true', 'yes'], true);
    }

    protected function parseNullableString(?string $value): ?string
    {
        if ($value === null || trim($value) === '' || trim($value) === '-') {
            return null;
        }

        return trim($value);
    }
}
