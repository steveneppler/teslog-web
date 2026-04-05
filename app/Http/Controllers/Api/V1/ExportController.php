<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\Drive;
use App\Models\VehicleState;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
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
                        $drive->vehicle->name,
                        $drive->start_address,
                        $drive->end_address,
                        $user->convertDistance($drive->distance),
                        $drive->energy_used_kwh,
                        $user->convertEfficiency($drive->efficiency),
                        $drive->start_battery_level,
                        $drive->end_battery_level,
                        $user->convertSpeed($drive->max_speed),
                        $user->convertSpeed($drive->avg_speed),
                        $drive->tag,
                        $drive->notes,
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
                            $state->gear,
                            $state->charger_power,
                            $state->charger_voltage,
                            $state->charger_current,
                            $state->charge_limit_soc,
                            $state->charge_state,
                            $state->energy_remaining,
                            $state->software_version,
                            $state->state,
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
                        $charge->vehicle->name,
                        $charge->place?->name ?? $charge->address,
                        $charge->charge_type?->value,
                        $charge->energy_added_kwh,
                        $charge->cost,
                        $charge->start_battery_level,
                        $charge->end_battery_level,
                        $charge->max_charger_power,
                        $charge->tag,
                        $charge->notes,
                    ]);
                }
            });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="teslog-charges.csv"',
        ]);
    }
}
