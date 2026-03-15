<?php

namespace App\Livewire;

use App\Models\BatteryHealth;
use App\Models\Charge;
use App\Models\Drive;
use App\Models\VehicleState;
use App\Services\TeslaCommandService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $user = Auth::user();
        $userTz = $user->userTz();
        $vehicles = $user->vehicles()
            ->where('show_on_dashboard', true)
            ->with('latestState')
            ->get();

        $vehicleIds = $vehicles->pluck('id');

        // Health warnings
        $health = Cache::get('teslog:health');
        $schedulerLastRun = Cache::get('teslog:scheduler_last_run');
        $warnings = [];

        if (! $schedulerLastRun) {
            $warnings[] = [
                'severity' => 'critical',
                'message' => 'Scheduler is not running. Token refresh, telemetry health checks, and other background tasks will not execute. Run: php artisan schedule:work',
            ];
        } elseif (now()->diffInMinutes($schedulerLastRun) > 20) {
            $warnings[] = [
                'severity' => 'critical',
                'message' => 'Scheduler has not run in ' . now()->diffForHumans($schedulerLastRun, true) . '. It may have stopped.',
            ];
        }

        if ($health) {
            foreach ($health['vehicles'] ?? [] as $vehicleData) {
                foreach ($vehicleData['warnings'] as $w) {
                    $warnings[] = array_merge($w, ['vehicle' => $vehicleData['vehicle_name']]);
                }
            }
            foreach ($health['system'] ?? [] as $w) {
                $warnings[] = $w;
            }
        }

        foreach ($vehicles as $vehicle) {
            if ($vehicle->latestState
                && $vehicle->latestState->timestamp->lt(now()->subHours(6))
                && ! in_array($vehicle->latestState->state, ['sleeping', 'idle'])) {
                $alreadyWarned = collect($warnings)->contains(fn ($w) => ($w['type'] ?? '') === 'stale_data' && ($w['vehicle'] ?? '') === ($vehicle->name ?: $vehicle->vin));
                if (! $alreadyWarned) {
                    $warnings[] = [
                        'type' => 'stale_data',
                        'severity' => 'warning',
                        'vehicle' => $vehicle->name ?: $vehicle->vin,
                        'message' => 'No telemetry data received in ' . round($vehicle->latestState->timestamp->diffInHours(now())) . ' hours.',
                    ];
                }
            }
        }

        // Rolling 7-day stats
        $weekStart = Carbon::now($userTz)->subDays(6)->startOfDay()->utc();
        $weekDrives = Drive::whereIn('vehicle_id', $vehicleIds)->where('started_at', '>=', $weekStart)->get();
        $weekCharges = Charge::whereIn('vehicle_id', $vehicleIds)->where('started_at', '>=', $weekStart)->get();

        $weekStats = [
            'drives' => $weekDrives->count(),
            'distance' => $weekDrives->sum('distance'),
            'energy_used' => $weekDrives->sum('energy_used_kwh'),
            'efficiency' => $weekDrives->count() > 0 ? $weekDrives->avg('efficiency') : null,
            'charges' => $weekCharges->count(),
            'energy_added' => $weekCharges->sum('energy_added_kwh'),
        ];

        // Recent drives (last 5)
        $recentDrives = Drive::whereIn('vehicle_id', $vehicleIds)
            ->with('startPlace', 'endPlace')
            ->orderByDesc('started_at')
            ->take(5)
            ->get();

        // Recent charges (last 5)
        $recentCharges = Charge::whereIn('vehicle_id', $vehicleIds)
            ->with('place')
            ->orderByDesc('started_at')
            ->take(5)
            ->get();

        // Battery sparkline — hourly averages over the week (aggregated in DB)
        $sparklinePoints = VehicleState::whereIn('vehicle_id', $vehicleIds)
            ->where('timestamp', '>=', $weekStart)
            ->whereNotNull('battery_level')
            ->selectRaw("strftime('%Y-%m-%d %H:00:00', timestamp) as hour, AVG(battery_level) as avg_bat, MIN(timestamp) as first_ts")
            ->groupByRaw("strftime('%Y-%m-%d %H:00:00', timestamp)")
            ->orderByRaw("hour")
            ->get()
            ->map(fn ($row) => [
                'ts' => Carbon::parse($row->first_ts)->timestamp * 1000,
                'bat' => round($row->avg_bat, 1),
            ])
            ->values()
            ->all();

        // Activity chart — miles driven and range added per day (rolling 7 days, 2 queries)
        $drivesByDay = $weekDrives->groupBy(fn ($d) => $d->started_at->tz($userTz)->format('Y-m-d'));
        $chargesByDay = $weekCharges->groupBy(fn ($c) => $c->started_at->tz($userTz)->format('Y-m-d'));

        $activityDays = [];
        $dayCursor = Carbon::now($userTz)->subDays(6)->startOfDay();
        for ($i = 0; $i < 7; $i++) {
            $dayKey = $dayCursor->format('Y-m-d');
            $dayDrives = $drivesByDay[$dayKey] ?? collect();
            $dayCharges = $chargesByDay[$dayKey] ?? collect();
            $rangeAdded = $dayCharges->sum(function ($c) {
                if ($c->start_rated_range !== null && $c->end_rated_range !== null) {
                    return max(0, $c->end_rated_range - $c->start_rated_range);
                }
                return ($c->energy_added_kwh ?? 0) * 3.5;
            });
            $activityDays[] = [
                'label' => $dayCursor->format('D'),
                'driven' => round($dayDrives->sum('distance'), 1),
                'charged' => round($rangeAdded, 1),
            ];
            $dayCursor->addDay();
        }

        // Totals
        $odometer = Drive::whereIn('vehicle_id', $vehicleIds)
            ->whereNotNull('end_odometer')
            ->orderByDesc('ended_at')
            ->value('end_odometer');

        $softwareVersion = $vehicles->pluck('firmware_version')->filter()->first();

        // Battery degradation per vehicle
        $vehicleDegradation = BatteryHealth::whereIn('vehicle_id', $vehicleIds)
            ->whereNotNull('degradation_pct')
            ->orderByDesc('recorded_at')
            ->get()
            ->unique('vehicle_id')
            ->keyBy('vehicle_id');

        if (count($sparklinePoints) > 1 && ! $this->sparklineInitialized) {
            $this->sparklineInitialized = true;
            $this->dispatch('sparkline-data', points: $sparklinePoints);
        }

        return view('livewire.dashboard', [
            'vehicles' => $vehicles,
            'warnings' => $warnings,
            'weekStats' => $weekStats,
            'recentDrives' => $recentDrives,
            'recentCharges' => $recentCharges,
            'odometer' => $odometer,
            'softwareVersion' => $softwareVersion,
            'sparklinePoints' => $sparklinePoints,
            'activityDays' => $activityDays,
            'userTz' => $userTz,
            'vehicleDegradation' => $vehicleDegradation,
        ]);
    }

    public bool $sparklineInitialized = false;

    public ?string $quickCommandResult = null;

    public ?bool $quickCommandSuccess = null;

    public function quickCommand(int $vehicleId, string $command): void
    {
        $user = Auth::user();
        $vehicle = $user->vehicles()->findOrFail($vehicleId);

        if (! $vehicle->tesla_vehicle_id) {
            return;
        }

        $allowed = ['lock', 'unlock', 'climate_on', 'climate_off', 'charge_start', 'charge_stop'];
        if (! in_array($command, $allowed)) {
            return;
        }

        $cacheKey = "vehicle-cmd:{$vehicleId}";
        if (! Cache::add($cacheKey, true, 5)) {
            $this->quickCommandResult = 'Please wait before sending another command.';
            $this->quickCommandSuccess = false;

            return;
        }

        $service = app(TeslaCommandService::class);
        $result = $service->execute($vehicle, $command, [], $user->id);

        $this->quickCommandSuccess = $result['success'];
        $this->quickCommandResult = $result['success']
            ? ucfirst(str_replace('_', ' ', $command)) . ' sent.'
            : ($result['error'] ?? 'Command failed.');

        // Optimistically update the vehicle state so the UI reflects the change immediately
        if ($result['success'] && $vehicle->latestState) {
            $updates = match ($command) {
                'lock' => ['locked' => true],
                'unlock' => ['locked' => false],
                'climate_on' => ['climate_on' => true],
                'climate_off' => ['climate_on' => false],
                'charge_start' => ['charge_state' => 'Charging'],
                'charge_stop' => ['charge_state' => 'Complete'],
                default => [],
            };

            if ($updates) {
                $vehicle->latestState->update($updates);
            }
        }
    }

}
