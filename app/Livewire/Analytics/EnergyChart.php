<?php

namespace App\Livewire\Analytics;

use App\Models\Charge;
use App\Models\Drive;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class EnergyChart extends Component
{
    #[Reactive]
    public string $vehicleFilter = '';

    public int $energyDays = 30;

    public function updateEnergyDays(int $days): void
    {
        $this->energyDays = $days;
    }

    public function render()
    {
        $user = Auth::user();
        $vehicleIds = $this->vehicleFilter
            ? collect([(int) $this->vehicleFilter])
            : $user->vehicles()->pluck('id');

        $energyUsed = Drive::whereIn('vehicle_id', $vehicleIds)
            ->where('started_at', '>=', now()->subDays($this->energyDays))
            ->select(
                DB::raw("date(started_at) as date"),
                DB::raw('sum(energy_used_kwh) as energy_used'),
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $energyAdded = Charge::whereIn('vehicle_id', $vehicleIds)
            ->where('started_at', '>=', now()->subDays($this->energyDays))
            ->select(
                DB::raw("date(started_at) as date"),
                DB::raw('sum(energy_added_kwh) as energy_added'),
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $allDates = $energyUsed->pluck('date')->merge($energyAdded->pluck('date'))->unique()->sort()->values();
        $usedByDate = $energyUsed->pluck('energy_used', 'date');
        $addedByDate = $energyAdded->pluck('energy_added', 'date');
        $energyChartData = $allDates->map(fn ($date) => [
            'date' => $date,
            'used' => round($usedByDate[$date] ?? 0, 1),
            'added' => round($addedByDate[$date] ?? 0, 1),
        ])->values();

        $this->dispatch('energy-chart-data', data: $energyChartData->all());

        return view('livewire.analytics.energy-chart', [
            'hasEnergyData' => $energyChartData->count() > 0,
        ]);
    }
}
