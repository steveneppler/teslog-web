<?php

namespace App\Livewire;

use App\Enums\ChargeType;
use App\Models\Charge;
use App\Models\Drive;
use App\Models\DrivePoint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class LifetimeMap extends Component
{
    public array $selectedVehicles = [];
    public bool $showCharges = false;
    public array $chargeTypes = ['supercharger', 'dc', 'ac'];

    public function mount()
    {
        $this->selectedVehicles = Auth::user()->vehicles()->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function render()
    {
        $vehicles = Auth::user()->vehicles()->get();
        $ownedIds = $vehicles->pluck('id');
        $vehicleIds = collect($this->selectedVehicles)
            ->map(fn ($id) => (int) $id)
            ->intersect($ownedIds);

        $drives = Drive::whereIn('vehicle_id', $vehicleIds)
            ->orderBy('started_at')
            ->get(['id', 'vehicle_id', 'started_at', 'ended_at', 'distance', 'energy_used_kwh', 'efficiency', 'max_speed', 'start_address', 'end_address']);

        $colors = ['#ef4444', '#3b82f6', '#22c55e', '#f59e0b', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'];
        $vehicleColorMap = [];
        $colorIdx = 0;
        foreach ($vehicles as $v) {
            $vehicleColorMap[$v->id] = $colors[$colorIdx % count($colors)];
            $colorIdx++;
        }

        // Load simplified points directly from DB using every Nth row to keep payload manageable
        $driveIds = $drives->pluck('id');

        // Charge stats (needed for stats bar regardless of showCharges toggle)
        $allCharges = Charge::whereIn('vehicle_id', $vehicleIds)
            ->get(['id', 'charge_type', 'energy_added_kwh', 'cost', 'started_at', 'ended_at', 'latitude', 'longitude', 'address', 'place_id']);
        if ($this->showCharges) {
            $allCharges->load('place:id,name');
        }

        $stats = $this->buildStats($drives, $allCharges);

        if ($driveIds->isEmpty()) {
            $this->dispatch('lifetime-map-updated', routes: [], charges: []);

            return view('livewire.lifetime-map', [
                'vehicles' => $vehicles,
                'vehicleColorMap' => $vehicleColorMap,
                'stats' => $stats,
            ]);
        }

        $totalPointCount = DrivePoint::whereIn('drive_id', $driveIds)->count();

        // Target ~15k points max for the map to stay responsive
        $maxPoints = 15000;
        $nth = max(1, (int) ceil($totalPointCount / $maxPoints));

        // Use ROW_NUMBER to sample every Nth point per drive
        $placeholders = $driveIds->map(fn () => '?')->implode(',');
        $sampledPoints = DB::select("
            SELECT drive_id, latitude, longitude FROM (
                SELECT drive_id, latitude, longitude,
                    ROW_NUMBER() OVER (PARTITION BY drive_id ORDER BY timestamp) as rn,
                    COUNT(*) OVER (PARTITION BY drive_id) as total
                FROM drive_points
                WHERE drive_id IN ({$placeholders})
                AND latitude IS NOT NULL AND longitude IS NOT NULL
            ) sub
            WHERE rn = 1 OR rn = total OR rn % ? = 0
        ", [...$driveIds->all(), $nth]);

        // Group by drive_id
        $pointsByDrive = collect($sampledPoints)->groupBy('drive_id');

        $routes = [];
        $totalPoints = 0;
        foreach ($drives as $drive) {
            $pts = $pointsByDrive[$drive->id] ?? collect();
            if ($pts->count() < 2) {
                continue;
            }

            $coords = $pts->map(fn ($p) => [$p->latitude, $p->longitude])->values()->all();
            $totalPoints += count($coords);

            $routes[] = [
                'coords' => $coords,
                'color' => $vehicleColorMap[$drive->vehicle_id] ?? '#ef4444',
            ];
        }

        // Charge markers (filter from already-loaded collection)
        $chargeMarkers = [];
        if ($this->showCharges && ! empty($this->chargeTypes)) {
            $chargeMarkers = $allCharges
                ->filter(fn ($c) => $c->latitude && $c->longitude && in_array($c->charge_type?->value, $this->chargeTypes))
                ->groupBy(fn ($c) => round($c->latitude, 4) . ',' . round($c->longitude, 4))
                ->map(function ($group) {
                    $first = $group->first();

                    return [
                        'lat' => $first->latitude,
                        'lng' => $first->longitude,
                        'label' => $first->place?->name ?? $first->address ?? 'Unknown',
                        'count' => $group->count(),
                        'energy' => round($group->sum('energy_added_kwh'), 1),
                        'type' => $first->charge_type?->value,
                    ];
                })
                ->values()
                ->all();
        }

        $this->dispatch('lifetime-map-updated', routes: $routes, charges: $chargeMarkers);

        return view('livewire.lifetime-map', [
            'vehicles' => $vehicles,
            'vehicleColorMap' => $vehicleColorMap,
            'stats' => $stats,
        ]);
    }

    private function buildStats($drives, $charges): array
    {
        $totalDistance = $drives->sum('distance');
        $totalEnergyUsed = $drives->sum('energy_used_kwh');
        $totalEnergyAdded = $charges->sum('energy_added_kwh');
        $totalChargeCost = $charges->sum('cost');

        // Drive time in hours
        $totalDriveSeconds = $drives->sum(fn ($d) => $d->ended_at && $d->started_at
            ? $d->started_at->diffInSeconds($d->ended_at)
            : 0);

        // Date range
        $firstDrive = $drives->min('started_at');
        $lastDrive = $drives->max('started_at');

        // Unique locations (charge locations)
        $uniqueChargeLocations = $charges
            ->filter(fn ($c) => $c->latitude && $c->longitude)
            ->groupBy(fn ($c) => round($c->latitude, 4) . ',' . round($c->longitude, 4))
            ->count();

        return [
            'drives' => $drives->count(),
            'distance' => $totalDistance,
            'energy_used' => $totalEnergyUsed,
            'mi_per_kwh' => $totalDistance > 0 && $totalEnergyUsed > 0
                ? round($totalDistance / $totalEnergyUsed, 1)
                : null,
            'drive_hours' => round($totalDriveSeconds / 3600, 1),
            'max_speed' => $drives->max('max_speed'),
            'charges' => $charges->count(),
            'energy_added' => $totalEnergyAdded,
            'charge_cost' => $totalChargeCost > 0 ? $totalChargeCost : null,
            'charge_locations' => $uniqueChargeLocations,
            'first_drive' => $firstDrive,
            'last_drive' => $lastDrive,
        ];
    }
}
