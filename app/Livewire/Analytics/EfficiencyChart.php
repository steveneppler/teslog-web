<?php

namespace App\Livewire\Analytics;

use App\Models\Drive;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class EfficiencyChart extends Component
{
    #[Reactive]
    public string $vehicleFilter = '';

    public int $efficiencyDays = 90;

    public function updateEfficiencyDays(int $days): void
    {
        $this->efficiencyDays = $days;
    }

    public function render()
    {
        $user = Auth::user();
        $vehicleIds = $this->vehicleFilter
            ? collect([(int) $this->vehicleFilter])
            : $user->vehicles()->pluck('id');

        $efficiencyData = Drive::whereIn('vehicle_id', $vehicleIds)
            ->where('started_at', '>=', now()->subDays($this->efficiencyDays))
            ->whereNotNull('efficiency')
            ->select(
                DB::raw("date(started_at) as date"),
                DB::raw('avg(efficiency) as avg_efficiency'),
                DB::raw('avg(outside_temp_avg) as avg_temp'),
                DB::raw('count(*) as drive_count'),
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'avg_efficiency' => round($user->convertEfficiency($row->avg_efficiency), 0),
                'avg_temp_raw' => $row->avg_temp !== null
                    ? round($user->convertTemp($row->avg_temp), 0)
                    : null,
                'drive_count' => $row->drive_count,
            ]);

        $effUnit = $user->efficiencyUnit();
        $tempUnit = $user->tempUnit();

        $this->dispatch('efficiency-chart-data', data: $efficiencyData->values()->all(), effUnit: $effUnit, tempUnit: $tempUnit);

        return view('livewire.analytics.efficiency-chart', [
            'hasEfficiencyData' => $efficiencyData->count() > 1,
        ]);
    }
}
