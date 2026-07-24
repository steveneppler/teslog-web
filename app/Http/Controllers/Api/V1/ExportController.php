<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\Drive;
use App\Models\TelemetryRaw;
use App\Models\VehicleState;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Neutralize spreadsheet formula injection: a cell beginning with
     * = + - @ (or tab/CR/LF) is treated as a formula by Excel/Sheets.
     */
    private function csvSafe($value)
    {
        if (is_string($value) && $value !== '' && str_contains("=+-@\t\r\n", $value[0])) {
            return "'".$value;
        }

        return $value;
    }

    public function drives(Request $request): StreamedResponse
    {
        $vehicleIds = $request->user()->vehicles()->pluck('id');

        $query = Drive::whereIn('vehicle_id', $vehicleIds)
            ->with('vehicle')
            ->orderByDesc('started_at');

        if ($request->has('from')) {
            $query->where('started_at', '>=', Carbon::parse($request->input('from'))->utc());
        }
        if ($request->has('to')) {
            $query->where('started_at', '<=', Carbon::parse($request->input('to'))->utc());
        }
        if ($request->has('tag')) {
            $query->where('tag', $request->input('tag'));
        }

        $user = $request->user();

        return new StreamedResponse(function () use ($query, $user) {
            $handle = fopen('php://output', 'w');
            $du = $user->distanceUnit();
            $su = $user->speedUnit();
            $eu = $user->efficiencyUnit();
            fputcsv($handle, [
                'Date', 'Vehicle', 'From', 'To', "Distance ({$du})", 'Energy (kWh)',
                "Efficiency ({$eu})", 'Start Battery %', 'End Battery %',
                "Max Speed ({$su})", "Avg Speed ({$su})", 'Tag', 'Notes',
            ]);

            $query->chunk(500, function ($drives) use ($handle, $user) {
                foreach ($drives as $drive) {
                    fputcsv($handle, [
                        $drive->started_at->toIso8601String(),
                        $this->csvSafe($drive->vehicle->name),
                        $this->csvSafe($drive->start_address),
                        $this->csvSafe($drive->end_address),
                        $user->convertDistance($drive->distance),
                        $drive->energy_used_kwh,
                        $user->convertEfficiency($drive->efficiency),
                        $drive->start_battery_level,
                        $drive->end_battery_level,
                        $user->convertSpeed($drive->max_speed),
                        $user->convertSpeed($drive->avg_speed),
                        $this->csvSafe($drive->tag),
                        $this->csvSafe($drive->notes),
                    ]);
                }
            });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="teslog-drives.csv"',
        ]);
    }

    /**
     * Export raw vehicle states as CSV (TeslaFi-compatible format).
     * Exported by month so files are manageable and match TeslaFi's export pattern.
     */
    public function raw(Request $request): StreamedResponse
    {
        $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'month' => 'required|date_format:Y-m',
        ]);

        $vehicle = $request->user()->vehicles()->findOrFail($request->vehicle_id);
        $userTz = $request->user()->userTz();

        $monthStart = Carbon::parse($request->month, $userTz)->startOfMonth()->utc();
        $monthEnd = Carbon::parse($request->month, $userTz)->endOfMonth()->utc();

        $filename = sprintf('teslog-%s-%s.csv', $vehicle->vin ?? $vehicle->name, $request->month);

        $headers = [
            'Date', 'battery_level', 'rated_battery_range_km', 'ideal_battery_range_km',
            'speed', 'power', 'odometer', 'latitude', 'longitude', 'heading', 'elevation',
            'inside_temp', 'outside_temp', 'locked', 'sentry_mode', 'is_climate_on',
            'Shift State', 'charger_power', 'charger_voltage', 'charger_actual_current',
            'charge_limit_soc', 'charging_state', 'usable_battery_level', 'car_version',
            'state',
        ];

        return response()->streamDownload(function () use ($vehicle, $monthStart, $monthEnd, $userTz, $headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);

            VehicleState::where('vehicle_id', $vehicle->id)
                ->where('timestamp', '>=', $monthStart)
                ->where('timestamp', '<=', $monthEnd)
                ->orderBy('timestamp')
                ->chunk(1000, function ($states) use ($out, $userTz) {
                    foreach ($states as $state) {
                        fputcsv($out, [
                            $state->timestamp->tz($userTz)->format('Y-m-d H:i:s'),
                            $state->battery_level,
                            $state->rated_range,
                            $state->ideal_range,
                            $state->speed,
                            $state->power,
                            $state->odometer,
                            $state->latitude,
                            $state->longitude,
                            $state->heading,
                            $state->elevation,
                            $state->inside_temp,
                            $state->outside_temp,
                            $state->locked !== null ? ($state->locked ? '1' : '0') : '',
                            $state->sentry_mode !== null ? ($state->sentry_mode ? '1' : '0') : '',
                            $state->climate_on !== null ? ($state->climate_on ? '1' : '0') : '',
                            $this->csvSafe($state->gear),
                            $state->charger_power,
                            $state->charger_voltage,
                            $state->charger_current,
                            $state->charge_limit_soc,
                            $this->csvSafe($state->charge_state),
                            $state->energy_remaining,
                            $this->csvSafe($state->software_version),
                            $this->csvSafe($state->state),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function charges(Request $request): StreamedResponse
    {
        $vehicleIds = $request->user()->vehicles()->pluck('id');

        $query = Charge::whereIn('vehicle_id', $vehicleIds)
            ->with('vehicle', 'place')
            ->orderByDesc('started_at');

        if ($request->has('from')) {
            $query->where('started_at', '>=', Carbon::parse($request->input('from'))->utc());
        }
        if ($request->has('to')) {
            $query->where('started_at', '<=', Carbon::parse($request->input('to'))->utc());
        }

        return new StreamedResponse(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Date', 'Vehicle', 'Location', 'Type', 'Energy Added (kWh)',
                'Cost', 'Start Battery %', 'End Battery %', 'Max Power (kW)',
                'Tag', 'Notes',
            ]);

            $query->chunk(500, function ($charges) use ($handle) {
                foreach ($charges as $charge) {
                    fputcsv($handle, [
                        $charge->started_at->toIso8601String(),
                        $this->csvSafe($charge->vehicle->name),
                        $this->csvSafe($charge->place?->name ?? $charge->address),
                        $charge->charge_type?->value,
                        $charge->energy_added_kwh,
                        $charge->cost,
                        $charge->start_battery_level,
                        $charge->end_battery_level,
                        $charge->max_charger_power,
                        $this->csvSafe($charge->tag),
                        $this->csvSafe($charge->notes),
                    ]);
                }
            });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="teslog-charges.csv"',
        ]);
    }

    /**
     * Stream the records currently visible on the Debug page (both tables) as JSON
     * so users can attach the file when reporting an issue.
     */
    public function debug(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->debug_mode, 403);

        $userVehicleIds = $user->vehicles()->pluck('id');

        $vehicle = null;
        if ($request->filled('vehicle_id')) {
            $vehicle = $user->vehicles()->findOrFail((int) $request->input('vehicle_id'));
            $vehicleIds = collect([$vehicle->id]);
        } else {
            $vehicleIds = $userVehicleIds;
        }

        $tz = $user->userTz();
        $fromUtc = $request->filled('from') ? Carbon::parse($request->input('from'), $tz)->utc() : null;
        $toUtc = $request->filled('to') ? Carbon::parse($request->input('to'), $tz)->utc() : null;
        $stateFilter = $request->input('state') ?: null;
        $fieldFilter = $request->input('field') ?: null;

        $vinSlug = $vehicle ? ($vehicle->vin ?: $vehicle->name) : 'all';
        $vinSlug = preg_replace('/[^A-Za-z0-9_-]+/', '', (string) $vinSlug) ?: 'all';
        $filename = sprintf('teslog-debug-%s-%s.json', $vinSlug, now()->format('Ymd-His'));

        return response()->streamDownload(function () use ($vehicle, $vehicleIds, $fromUtc, $toUtc, $stateFilter, $fieldFilter) {
            echo '{';
            echo '"exported_at":'.json_encode(now()->toIso8601String()).',';
            echo '"app_version":'.json_encode(config('app.version')).',';
            echo '"filters":'.json_encode([
                'vehicle_id' => $vehicle?->id,
                'from' => $fromUtc?->toIso8601String(),
                'to' => $toUtc?->toIso8601String(),
                'state' => $stateFilter,
                'field' => $fieldFilter,
            ]).',';
            echo '"vehicle":'.json_encode($vehicle ? [
                'id' => $vehicle->id,
                'vin' => $vehicle->vin,
                'name' => $vehicle->name,
                'model' => $vehicle->model,
            ] : null).',';

            echo '"processed_states":[';
            $first = true;
            $statesQuery = VehicleState::whereIn('vehicle_id', $vehicleIds)->orderBy('id');
            if ($fromUtc) {
                $statesQuery->where('timestamp', '>=', $fromUtc);
            }
            if ($toUtc) {
                $statesQuery->where('timestamp', '<=', $toUtc);
            }
            if ($stateFilter) {
                $statesQuery->where('state', $stateFilter);
            }
            if ($fieldFilter) {
                $statesQuery->where(function ($q) use ($fieldFilter) {
                    $q->where('charge_state', 'like', "%{$fieldFilter}%")
                        ->orWhere('state', 'like', "%{$fieldFilter}%")
                        ->orWhere('gear', 'like', "%{$fieldFilter}%")
                        ->orWhere('software_version', 'like', "%{$fieldFilter}%");
                });
            }
            $statesQuery->chunkById(1000, function ($states) use (&$first) {
                foreach ($states as $state) {
                    echo $first ? '' : ',';
                    echo json_encode($state->toArray());
                    $first = false;
                }
                flush();
            });
            echo '],';

            echo '"raw_telemetry":[';
            $first = true;
            $rawQuery = TelemetryRaw::whereIn('vehicle_id', $vehicleIds)->orderBy('id');
            if ($fromUtc) {
                $rawQuery->whereRaw("strftime('%Y-%m-%d %H:%M:%S', timestamp) >= ?", [$fromUtc->format('Y-m-d H:i:s')]);
            }
            if ($toUtc) {
                $rawQuery->whereRaw("strftime('%Y-%m-%d %H:%M:%S', timestamp) <= ?", [$toUtc->format('Y-m-d H:i:s')]);
            }
            if ($fieldFilter) {
                $rawQuery->where('field_name', 'like', "%{$fieldFilter}%");
            }
            $rawQuery->chunkById(1000, function ($rows) use (&$first) {
                foreach ($rows as $row) {
                    echo $first ? '' : ',';
                    echo json_encode($row->toArray());
                    $first = false;
                }
                flush();
            });
            echo ']';

            echo '}';
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }
}
