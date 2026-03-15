<?php

namespace App\Livewire;

use App\Models\BatteryHealth as BatteryHealthModel;
use App\Models\Vehicle;
use App\Models\VehicleState;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class BatteryHealth extends Component
{
    public Vehicle $vehicle;

    public function mount(Vehicle $vehicle): void
    {
        // Ensure the vehicle belongs to the authenticated user
        abort_unless($vehicle->user_id === Auth::id(), 403);

        $this->vehicle = $vehicle;
    }

    public function render()
    {
        $user = Auth::user();

        $healthRecords = BatteryHealthModel::where('vehicle_id', $this->vehicle->id)
            ->orderByDesc('recorded_at')
            ->take(365)
            ->get();

        // Current stats
        $latest = $healthRecords->first();
        $currentDegradation = $latest?->degradation_pct;

        // Calculate original range at full from best-ever high-SOC reading
        $bestState = VehicleState::where('vehicle_id', $this->vehicle->id)
            ->whereNotNull('rated_range')
            ->where('battery_level', '>=', 95)
            ->where('rated_range', '>', 0)
            ->orderByDesc('rated_range')
            ->first();

        $originalRangeAtFull = $bestState
            ? $bestState->rated_range / $bestState->battery_level * 100
            : null;

        $currentRangeAtFull = $latest && $latest->battery_level > 0
            ? $latest->rated_range / $latest->battery_level * 100
            : null;

        // Chart data (chronological for display)
        $chartData = $healthRecords->reverse()->values()->map(fn ($r) => [
            'date' => $r->recorded_at->format('Y-m-d'),
            'range_at_full' => $r->battery_level > 0 ? round($r->rated_range / $r->battery_level * 100, 1) : null,
            'degradation' => $r->degradation_pct,
        ])->all();

        if (count($chartData) > 1) {
            $this->dispatch('health-chart-data', data: $chartData);
        }

        return view('livewire.battery-health', [
            'healthRecords' => $healthRecords->take(30),
            'currentDegradation' => $currentDegradation,
            'originalRangeAtFull' => $originalRangeAtFull,
            'currentRangeAtFull' => $currentRangeAtFull,
            'chartData' => $chartData,
            'user' => $user,
        ]);
    }
}
